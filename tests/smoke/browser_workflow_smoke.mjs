import { mkdtemp, rm } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn } from 'node:child_process';

const baseUrl = (process.argv[2] || 'http://restaurant.test').replace(/\/$/, '');
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function chromePath() {
  const candidates = [
    process.env.CHROME_PATH,
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    join(process.env.LOCALAPPDATA || '', 'Google\\Chrome\\Application\\chrome.exe'),
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  ].filter(Boolean);

  const found = candidates.find((candidate) => existsSync(candidate));
  if (!found) throw new Error('Chrome or Edge executable was not found. Set CHROME_PATH.');
  return found;
}

async function fetchJson(url, options = {}) {
  const response = await fetch(url, options);
  if (!response.ok) throw new Error(`${url} returned HTTP ${response.status}`);
  return response.json();
}

async function waitForDebugPort(port) {
  for (let i = 0; i < 50; i += 1) {
    try {
      return await fetchJson(`http://127.0.0.1:${port}/json/version`);
    } catch {
      await wait(100);
    }
  }
  throw new Error('Chrome DevTools port did not become available.');
}

function createCdpClient(wsUrl) {
  const socket = new WebSocket(wsUrl);
  let id = 0;
  const pending = new Map();
  const events = [];

  socket.addEventListener('message', (message) => {
    const raw = typeof message.data === 'string'
      ? message.data
      : Buffer.from(message.data).toString('utf8');
    const data = JSON.parse(raw);
    if (data.id && pending.has(data.id)) {
      const { resolve, reject } = pending.get(data.id);
      pending.delete(data.id);
      data.error ? reject(new Error(data.error.message)) : resolve(data.result || {});
      return;
    }
    events.push(data);
  });

  return new Promise((resolve, reject) => {
    socket.addEventListener('open', () => {
      resolve({
        events,
        send(method, params = {}) {
          id += 1;
          const currentId = id;
          socket.send(JSON.stringify({ id: currentId, method, params }));
          return new Promise((sendResolve, sendReject) => {
            const timeout = setTimeout(() => {
              pending.delete(currentId);
              sendReject(new Error(`${method} timed out`));
            }, 10000);
            pending.set(currentId, {
              resolve(result) {
                clearTimeout(timeout);
                sendResolve(result);
              },
              reject(error) {
                clearTimeout(timeout);
                sendReject(error);
              },
            });
          });
        },
        close() {
          socket.close();
        },
      });
    });
    socket.addEventListener('error', () => reject(new Error('WebSocket connection failed.')));
  });
}

function eventToIssue(event) {
  if (event.method === 'Runtime.exceptionThrown') {
    const details = event.params?.exceptionDetails;
    return `exception: ${details?.text || 'unknown exception'} at line ${details?.lineNumber ?? '?'}`;
  }
  if (event.method === 'Runtime.consoleAPICalled' && event.params?.type === 'error') {
    const args = event.params.args || [];
    return `console.error: ${args.map((arg) => arg.value || arg.description || arg.type).join(' ')}`;
  }
  if (event.method === 'Log.entryAdded' && event.params?.entry?.level === 'error') {
    const entry = event.params.entry;
    const sourceUrl = entry.url ? ` (${entry.url})` : '';
    return `log.error: ${entry.text || 'unknown log error'}${sourceUrl}`;
  }
  return null;
}

async function openTarget(port, url) {
  const encoded = encodeURIComponent(url);
  try {
    return await fetchJson(`http://127.0.0.1:${port}/json/new?${encoded}`, { method: 'PUT' });
  } catch {
    return fetchJson(`http://127.0.0.1:${port}/json/new?${encoded}`);
  }
}

async function closeTarget(port, targetId) {
  try {
    await fetchJson(`http://127.0.0.1:${port}/json/close/${targetId}`);
  } catch {
    // Best effort cleanup.
  }
}

async function evaluate(client, expression) {
  const result = await client.send('Runtime.evaluate', {
    expression,
    awaitPromise: true,
    returnByValue: true,
  });

  if (result.exceptionDetails) {
    throw new Error(result.exceptionDetails.text || 'Runtime.evaluate failed');
  }
  return result.result?.value;
}

async function waitForLoad(client, url) {
  await client.send('Runtime.enable');
  await client.send('Log.enable');
  await client.send('Page.enable');
  await client.send('Page.navigate', { url });

  const start = Date.now();
  while (Date.now() - start < 10000) {
    if (client.events.some((event) => event.method === 'Page.loadEventFired')) return;
    await wait(100);
  }
  throw new Error(`${url} did not finish loading within 10 seconds.`);
}

async function waitForExpression(client, expression, label, timeoutMs = 10000) {
  const start = Date.now();
  while (Date.now() - start < timeoutMs) {
    if (await evaluate(client, expression)) return;
    await wait(150);
  }
  throw new Error(`${label} did not become true.`);
}

function assertNoConsoleIssues(client, label) {
  const issues = client.events.map(eventToIssue).filter(Boolean);
  if (issues.length > 0) {
    throw new Error(`${label} browser console failed:\n${issues.map((issue) => `- ${issue}`).join('\n')}`);
  }
}

async function runWorkflow(port, name, path, workflow) {
  const url = `${baseUrl}${path}`;
  const target = await openTarget(port, url);
  const client = await createCdpClient(target.webSocketDebuggerUrl);

  try {
    await waitForLoad(client, url);
    await wait(1000);
    assertNoConsoleIssues(client, name);
    await workflow(client);
    await wait(500);
    assertNoConsoleIssues(client, name);
    console.log(`PASS browser_workflow ${name}`);
  } finally {
    client.close();
    await closeTarget(port, target.id);
  }
}

const workflows = [
  ['home_cart_toggle', '/', async (client) => {
    await waitForExpression(client, "document.querySelectorAll('#public-menu-grid .menu-item, #public-menu-grid .menu-card, #public-menu-grid > *').length > 0", 'menu render');
    await evaluate(client, "document.querySelector('#floating-cart-btn').click(); true");
    await waitForExpression(client, "!document.querySelector('#floating-cart-popup').classList.contains('hidden')", 'cart opens');
    await evaluate(client, "document.querySelector('[data-action=\"toggle-cart\"]').click(); true");
    await waitForExpression(client, "document.querySelector('#floating-cart-popup').classList.contains('hidden')", 'cart closes');
  }],
  ['booking_preorder_drawer', '/booking.html', async (client) => {
    await waitForExpression(client, "document.querySelectorAll('.table-box, .table-card, [data-table-id]').length > 0 || document.querySelector('#table-map-container')?.textContent.length > 20", 'booking table map');
    await evaluate(client, "document.querySelector('#toggle-preorder').click(); true");
    await waitForExpression(client, "getComputedStyle(document.querySelector('#preorder-section')).display !== 'none'", 'preorder section opens');
    await evaluate(client, "document.querySelector('[data-action=\"open-preorder-drawer\"]').click(); true");
    await waitForExpression(client, "!document.querySelector('#preorder-drawer').classList.contains('hidden')", 'preorder drawer opens');
    await evaluate(client, "document.querySelector('#drawer-search').value = 'ph'; document.querySelector('#drawer-search').dispatchEvent(new Event('input', { bubbles: true })); true");
    await evaluate(client, "document.querySelector('[data-action=\"close-preorder-drawer\"]').click(); true");
    await waitForExpression(client, "document.querySelector('#preorder-drawer').classList.contains('hidden')", 'preorder drawer closes');
  }],
  ['login_tabs', '/login.html', async (client) => {
    await evaluate(client, "document.querySelector('[data-tab=\"register\"]').click(); true");
    await waitForExpression(client, "document.querySelector('#tab-register').classList.contains('active')", 'register tab active');
    await evaluate(client, "document.querySelector('[data-tab=\"login\"]').click(); true");
    await waitForExpression(client, "document.querySelector('#tab-login').classList.contains('active')", 'login tab active');
  }],
  ['forgot_password_back_link', '/forgot_password.html', async (client) => {
    await evaluate(client, "document.querySelector('#step1').classList.remove('active'); document.querySelector('#step2').classList.add('active'); document.querySelector('#btnBackToEmail').click(); true");
    await waitForExpression(client, "document.querySelector('#step1').classList.contains('active')", 'forgot password back to email');
  }],
  ['admin_login_page', '/admin/', async (client) => {
    await waitForExpression(client, "document.querySelector('#adminLoginForm') && document.querySelector('#btnLogin')", 'admin login form');
    await evaluate(client, `
      document.querySelector('#email').value = 'admin.demo@example.test';
      document.querySelector('#password').value = 'ChangeMeDemo123!';
      document.querySelector('#adminLoginForm').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
      true;
    `);
    await waitForExpression(client, "location.pathname.endsWith('/admin/dashboard.php')", 'admin login redirect');
  }],
  ['admin_logs_controls', '/admin/logs.php', async (client) => {
    await waitForExpression(client, "document.querySelector('#healthGrid') && document.querySelector('#logRows')", 'admin logs layout');
    await evaluate(client, "document.querySelector('[data-action=\"refresh-all\"]').click(); true");
  }],
];

const port = 10400 + Math.floor(Math.random() * 1000);
const userDataDir = await mkdtemp(join(tmpdir(), 'restaurant-workflow-smoke-'));
const chrome = spawn(chromePath(), [
  '--headless=new',
  `--remote-debugging-port=${port}`,
  `--user-data-dir=${userDataDir}`,
  '--disable-gpu',
  '--disable-gpu-sandbox',
  '--disable-dev-shm-usage',
  '--no-sandbox',
  '--no-first-run',
  '--no-default-browser-check',
  '--remote-allow-origins=*',
  'about:blank',
], { stdio: 'ignore' });

try {
  await waitForDebugPort(port);
  for (const [name, path, workflow] of workflows) {
    await runWorkflow(port, name, path, workflow);
  }
} finally {
  chrome.kill();
  await wait(500);
  try {
    await rm(userDataDir, { recursive: true, force: true });
  } catch {
    // Chrome can hold a profile lock briefly after shutdown on Windows.
  }
}
