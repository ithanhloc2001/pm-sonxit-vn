<?php
$source_path = 'C:\Users\ASUS AI\.gemini\antigravity\brain\d7597a5c-1c39-48f9-9f81-92e5a9be556b\media__1780454191948.png';
$dest_path = __DIR__ . '/../page_home/image/hero_banner_model.png';

if (!file_exists($source_path)) {
    die("Source file does not exist: $source_path\n");
}

$info = getimagesize($source_path);
if (!$info) {
    die("Failed to read image info\n");
}

$width = $info[0];
$height = $info[1];
echo "Image dimensions: {$width}x{$height}\n";

// We want to crop the right side. Let's crop from 430px of width to 100%.
$crop_x = 430;
$crop_y = 0;
$crop_width = $width - $crop_x;
$crop_height = $height;

echo "Cropping parameters: X=$crop_x, Y=$crop_y, Width=$crop_width, Height=$crop_height\n";

$src_img = imagecreatefrompng($source_path);
if (!$src_img) {
    die("Failed to load source image\n");
}

$cropped_img = imagecrop($src_img, [
    'x' => $crop_x,
    'y' => $crop_y,
    'width' => $crop_width,
    'height' => $crop_height
]);

if (!$cropped_img) {
    die("Failed to crop image\n");
}

if (imagepng($cropped_img, $dest_path)) {
    echo "Successfully saved cropped image to: $dest_path\n";
} else {
    echo "Failed to save cropped image\n";
}

imagedestroy($src_img);
imagedestroy($cropped_img);
