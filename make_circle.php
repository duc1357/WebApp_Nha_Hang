<?php
// make_circle.php - Convert an image to a circle with transparent background
$srcFile = __DIR__ . '/photo/logo.png';
$destFile = __DIR__ . '/photo/favicon.png';

if (!file_exists($srcFile)) {
    die("Error: Source file not found.\n");
}

$src = imagecreatefrompng($srcFile);
if (!$src) {
    die("Error: Could not load PNG.\n");
}

$width = imagesx($src);
$height = imagesy($src);
$size = min($width, $height);

// Create a new true color image with transparent background
$dst = imagecreatetruecolor($size, $size);
imagesavealpha($dst, true);
$transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
imagefill($dst, 0, 0, $transparent);

// Create a mask
$mask = imagecreatetruecolor($size, $size);
$maskTransparent = imagecolorallocate($mask, 0, 0, 0); // Black for transparency
$maskSolid = imagecolorallocate($mask, 255, 255, 255); // White for solid
imagefill($mask, 0, 0, $maskTransparent);
imagefilledellipse($mask, $size/2, $size/2, $size, $size, $maskSolid);

// Copy pixels
$src_x = ($width - $size) / 2;
$src_y = ($height - $size) / 2;

for ($x = 0; $x < $size; $x++) {
    for ($y = 0; $y < $size; $y++) {
        $alpha = imagecolorsforindex($mask, imagecolorat($mask, $x, $y));
        if ($alpha['red'] > 0) { // If inside the circle
            $color = imagecolorsforindex($src, imagecolorat($src, $x + $src_x, $y + $src_y));
            $pixelColor = imagecolorallocatealpha($dst, $color['red'], $color['green'], $color['blue'], $color['alpha']);
            imagesetpixel($dst, $x, $y, $pixelColor);
        }
    }
}

imagepng($dst, $destFile);
imagedestroy($src);
imagedestroy($dst);
imagedestroy($mask);

echo "Success! Saved circular image to $destFile\n";
