<?php
function remove_white_background($image, $tolerance = 250) {
    // Görseli GD kütüphanesi ile yükle
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Beyaz pikseli tanımla
    $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);

    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            $rgb = imagecolorat($image, $x, $y);
            $colors = imagecolorsforindex($image, $rgb);

            // Beyaz rengine yakınsa şeffaf yap
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

    // Sınırları bul
    $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);

    // Yukarıdan kırp
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            if (imagecolorat($image, $x, $y) !== $transparent) {
                $top = $y;
                break 2;
            }
        }
    }

    // Aşağıdan kırp
    for ($y = $height - 1; $y >= 0; $y--) {
        for ($x = 0; $x < $width; $x++) {
            if (imagecolorat($image, $x, $y) !== $transparent) {
                $bottom = $y;
                break 2;
            }
        }
    }

    // Soldan kırp
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            if (imagecolorat($image, $x, $y) !== $transparent) {
                $left = $x;
                break 2;
            }
        }
    }

    // Sağdan kırp
    for ($x = $width - 1; $x >= 0; $x--) {
        for ($y = 0; $y < $height; $y++) {
            if (imagecolorat($image, $x, $y) !== $transparent) {
                $right = $x;
                break 2;
            }
        }
    }

    // Yeni boyutlar ve kırpılmış görüntü
    $new_width = $right - $left + 1;
    $new_height = $bottom - $top + 1;
    $cropped_image = imagecreatetruecolor($new_width, $new_height);
    imagealphablending($cropped_image, false);
    imagesavealpha($cropped_image, true);

    imagecopy($cropped_image, $image, 0, 0, $left, $top, $new_width, $new_height);

    return $cropped_image;
}

function place_image_on_canvas($image_path, $output_path, $canvas_width = 2000, $canvas_height = 2000, $padding = 250) {
    // Görseli yükle
    $image = imagecreatefrompng($image_path);

    // Beyaz arka planı temizle
    $image = remove_white_background($image);

    // Şeffaf alanları kırp
    $image = trim_transparent_borders($image);

    // Görselin boyutlarını al
    $img_width = imagesx($image);
    $img_height = imagesy($image);

    // Yeniden boyutlandırma oranını hesapla
    $target_height = $canvas_height - (2 * $padding);
    $target_width = $canvas_width;
    $scale_factor = min($target_width / $img_width, $target_height / $img_height);

    $new_width = (int)($img_width * $scale_factor);
    $new_height = (int)($img_height * $scale_factor);

    // Görseli yeniden boyutlandır
    $resized_image = imagecreatetruecolor($new_width, $new_height);
    imagealphablending($resized_image, false);
    imagesavealpha($resized_image, true);
    imagecopyresampled($resized_image, $image, 0, 0, 0, 0, $new_width, $new_height, $img_width, $img_height);

    // 2000x2000'lik şeffaf bir tuval oluştur
    $canvas = imagecreatetruecolor($canvas_width, $canvas_height);
    $transparent_color = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
    imagefill($canvas, 0, 0, $transparent_color);
    imagesavealpha($canvas, true);

    // Görseli ortalamak için pozisyonları hesapla
    $x_offset = ($canvas_width - $new_width) / 2;
    $y_offset = ($canvas_height - $new_height) / 2;

    // Görseli tuvale yerleştir
    imagecopy($canvas, $resized_image, $x_offset, $y_offset, 0, 0, $new_width, $new_height);

    // PNG olarak kaydet
    imagepng($canvas, $output_path);

    // Belleği boşalt
    imagedestroy($image);
    imagedestroy($resized_image);
    imagedestroy($canvas);

    echo "Image placed on canvas and saved as: " . $output_path . "\n";
}

// CSV'den barkod ve ürün kodlarını okuma
function read_barcodes_from_csv($csv_file) {
    $barcode_dict = [];
    if (($handle = fopen($csv_file, "r")) !== false) {
        while (($data = fgetcsv($handle, 1000, "\t")) !== false) {
            $barcode_dict[$data[0]] = $data[1];  // Barkod -> Ürün kodu eşlemesi
        }
        fclose($handle);
    }
    return $barcode_dict;
}

// Resimleri klasörden işleme
function process_images_from_folder($images_folder, $barcode_csv) {
    $barcode_dict = read_barcodes_from_csv($barcode_csv);

    // Geçerli tarihi al
    $current_date = date('Y-m-d');

    // "images/resized" klasörüne tarih eklenmiş klasör oluştur
    $resized_folder = $images_folder . "/resized_" . $current_date;
    if (!file_exists($resized_folder)) {
        mkdir($resized_folder, 0777, true);
    }

    // Klasördeki tüm JPG dosyalarını bul
    $files = glob($images_folder . "/*.jpg");
    foreach ($files as $file) {
        $filename = basename($file);
        $product_code = explode('-', $filename)[0];

        // Barkod bulunursa yeni dosya adını ürün koduna göre güncelle
        if (isset($barcode_dict[$product_code])) {
            $new_filename = $barcode_dict[$product_code] . ".png";
            $output_path = $resized_folder . "/" . $new_filename;
            place_image_on_canvas($file, $output_path);
        } else {
            echo "Barkod bulunamadı: " . $filename . "\n";
        }
    }
}

// Ana program
$images_folder = "images";
$barcode_csv = "product-barkod.csv";
process_images_from_folder($images_folder, $barcode_csv);
?>
