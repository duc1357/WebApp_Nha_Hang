import { mkdtemp, rm } from 'node:fs/promises';
import { existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn } from 'node:child_process';

const baseUrl = (process.argv[2] || 'http://restaurant.test').replace(/\/$/, '');
const urls = [
  '/',
  '/booking.html',
  '/profile.html',
  '/login.html',
  '/forgot_password.html',
  '/admin/',
  '/admin/bookings.php',
  '/admin/logs.php',
];

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
  if (!found) {
    throw new Error('Chrome or Edge executable was not found. Set CHROME_PATH to run browser console smoke.');
  }
  return found;
}

async function fetchJson(url, options = {}) {
  const response = await fetch(url, options);
  if (!response.ok) {
    throw new Error(`${url} returned HTTP ${response.status}`);
  }
  return response.json();
}

async function waitForDebugPort(port) {
  const versionUrl = `http://127.0.0.1:${port}/json/version`;
  for (let i = 0; i < 50; i += 1) {
    try {
      return await fetchJson(versionUrl);
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
      if (data.error) {
        reject(new Error(data.error.message));
      } else {
        resolve(data.result || {});
      }
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
    const text = args.map((arg) => arg.value || arg.description || arg.type).join(' ');
    return `console.error: ${text}`;
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

async function checkPage(port, path) {
  const url = `${baseUrl}${path}`;
  const target = await openTarget(port, url);
  const client = await createCdpClient(target.webSocketDebuggerUrl);

  try {
    await client.send('Runtime.enable');
    await client.send('Log.enable');
    await client.send('Page.enable');
    await client.send('Page.navigate', { url });

    const start = Date.now();
    let loaded = false;
    while (Date.now() - start < 10000) {
      if (client.events.some((event) => event.method === 'Page.loadEventFired')) {
        loaded = true;
        break;
      }
      await wait(100);
    }

    if (!loaded) {
      throw new Error(`${url} did not finish loading within 10 seconds.`);
    }

    await wait(1500);
    const issues = client.events.map(eventToIssue).filter(Boolean);
    if (issues.length > 0) {
      throw new Error(`${url} browser console failed:\n${issues.map((issue) => `- ${issue}`).join('\n')}`);
    }

    console.log(`PASS browser_console ${path}`);
  } finally {
    client.close();
    await closeTarget(port, target.id);
  }
}

const port = 9222 + Math.floor(Math.random() * 1000);
const userDataDir = await mkdtemp(join(tmpdir(), 'restaurant-browser-smoke-'));
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
  for (const path of urls) {
    await checkPage(port, path);
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
