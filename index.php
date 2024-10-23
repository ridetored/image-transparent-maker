<?php
function remove_white_background($image, $tolerance = 250) {
    // Load the image using GD library
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Define the white pixel to be replaced with transparency
    $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);

    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            $rgb = imagecolorat($image, $x, $y);
            $colors = imagecolorsforindex($image, $rgb);

            // If the pixel is close to white, make it transparent
            if ($colors['red'] > $tolerance && $colors['green'] > $tolerance && $colors['blue'] > $tolerance) {
                imagesetpixel($image, $x, $y, $transparent);
            }
        }
    }
    return $image;
}

function trim_transparent_borders($image) {
    $width = imagesx($image);
    $height = imagesy($image);

    $top = 0;
    $left = 0;
    $right = $width - 1;
    $bottom = $height - 1;

    // Define the transparent color
    $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);

    // Trim from the top
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            if (imagecolorat($image, $x, $y) !== $transparent) {
                $top = $y;
                break 2;
            }
        }
    }

    // Trim from the bottom
    for ($y = $height - 1; $y >= 0; $y--) {
        for ($x = 0; $x < $width; $x++) {
            if (imagecolorat($image, $x, $y) !== $transparent) {
                $bottom = $y;
                break 2;
            }
        }
    }

    // Trim from the left
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            if (imagecolorat($image, $x, $y) !== $transparent) {
                $left = $x;
                break 2;
            }
        }
    }

    // Trim from the right
    for ($x = $width - 1; $x >= 0; $x--) {
        for ($y = 0; $y < $height; $y++) {
            if (imagecolorat($image, $x, $y) !== $transparent) {
                $right = $x;
                break 2;
            }
        }
    }

    // Calculate new dimensions and crop the image
    $new_width = $right - $left + 1;
    $new_height = $bottom - $top + 1;
    $cropped_image = imagecreatetruecolor($new_width, $new_height);
    imagealphablending($cropped_image, false);
    imagesavealpha($cropped_image, true);

    imagecopy($cropped_image, $image, 0, 0, $left, $top, $new_width, $new_height);

    return $cropped_image;
}

function place_image_on_canvas($image_path, $output_path, $canvas_width = 2000, $canvas_height = 2000, $padding = 250) {
    // Load the image
    $image = imagecreatefrompng($image_path);

    // Remove the white background
    $image = remove_white_background($image);

    // Trim transparent borders
    $image = trim_transparent_borders($image);

    // Get image dimensions
    $img_width = imagesx($image);
    $img_height = imagesy($image);

    // Calculate the scaling factor
    $target_height = $canvas_height - (2 * $padding);
    $target_width = $canvas_width;
    $scale_factor = min($target_width / $img_width, $target_height / $img_height);

    $new_width = (int)($img_width * $scale_factor);
    $new_height = (int)($img_height * $scale_factor);

    // Resize the image
    $resized_image = imagecreatetruecolor($new_width, $new_height);
    imagealphablending($resized_image, false);
    imagesavealpha($resized_image, true);
    imagecopyresampled($resized_image, $image, 0, 0, 0, 0, $new_width, $new_height, $img_width, $img_height);

    // Create a 2000x2000 canvas with a transparent background
    $canvas = imagecreatetruecolor($canvas_width, $canvas_height);
    $transparent_color = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
    imagefill($canvas, 0, 0, $transparent_color);
    imagesavealpha($canvas, true);

    // Calculate the offsets to center the image
    $x_offset = ($canvas_width - $new_width) / 2;
    $y_offset = ($canvas_height - $new_height) / 2;

    // Place the image on the canvas
    imagecopy($canvas, $resized_image, $x_offset, $y_offset, 0, 0, $new_width, $new_height);

    // Save the canvas as a PNG
    imagepng($canvas, $output_path);

    // Free memory
    imagedestroy($image);
    imagedestroy($resized_image);
    imagedestroy($canvas);

    echo "Image placed on canvas and saved as: " . $output_path . "\n";
}

// Read barcodes and product codes from CSV
function read_barcodes_from_csv($csv_file) {
    $barcode_dict = [];
    if (($handle = fopen($csv_file, "r")) !== false) {
        while (($data = fgetcsv($handle, 1000, "\t")) !== false) {
            $barcode_dict[$data[0]] = $data[1];  // Map barcode to product code
        }
        fclose($handle);
    }
    return $barcode_dict;
}

// Process images from a folder
function process_images_from_folder($images_folder, $barcode_csv) {
    $barcode_dict = read_barcodes_from_csv($barcode_csv);

    // Get the current date
    $current_date = date('Y-m-d');

    // Create a new folder with the current date for resized images
    $resized_folder = $images_folder . "/resized_" . $current_date;
    if (!file_exists($resized_folder)) {
        mkdir($resized_folder, 0777, true);
    }

    // Find all JPG files in the folder
    $files = glob($images_folder . "/*.jpg");
    foreach ($files as $file) {
        $filename = basename($file);
        $product_code = explode('-', $filename)[0];

        // If the barcode is found, rename the file with the product code
        if (isset($barcode_dict[$product_code])) {
            $new_filename = $barcode_dict[$product_code] . ".png";
            $output_path = $resized_folder . "/" . $new_filename;
            place_image_on_canvas($file, $output_path);
        } else {
            echo "Barcode not found: " . $filename . "\n";
        }
    }
}

// Main program
$images_folder = "images";
$barcode_csv = "product-barkod.csv";
process_images_from_folder($images_folder, $barcode_csv);
?>
