<?php
/**
 * The GIL Business Suite mark.
 *
 * A navy tile — the document title bar's own colour — with a white "G" and the
 * orange drill-arrow accent the A/R screen uses for its Choose From List
 * buttons. Two colours the application already owns, so the tab matches the
 * panel rather than introducing a third palette.
 *
 * Drawn at 4x and downsampled, because GD's own antialiasing on curves at
 * 16px is worse than shrinking a clean 64px render.
 *
 * Run with: php bin/make-favicon.php
 */

const NAVY   = [0x1f, 0x4e, 0x79];
const ORANGE = [0xe2, 0x57, 0x1f];
const FONT   = __DIR__.'/../vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf';

function tile(int $size, bool $rounded = true): GdImage
{
    $scale = 4;
    $s = $size * $scale;

    $im = imagecreatetruecolor($s, $s);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);

    $navy = imagecolorallocate($im, ...NAVY);
    $orange = imagecolorallocate($im, ...ORANGE);
    $white = imagecolorallocate($im, 255, 255, 255);

    // Rounded square, or a full bleed for the maskable icon.
    $r = $rounded ? (int) ($s * 0.22) : 0;
    imagefilledrectangle($im, $r, 0, $s - $r, $s, $navy);
    imagefilledrectangle($im, 0, $r, $s, $s - $r, $navy);
    if ($rounded) {
        foreach ([[$r, $r], [$s - $r, $r], [$r, $s - $r], [$s - $r, $s - $r]] as [$cx, $cy]) {
            imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $navy);
        }
    }

    // The "G", optically centred: DejaVu's cap sits high in its box.
    $pt = $s * 0.52;
    $box = imagettfbbox($pt, 0, FONT, 'G');
    $w = $box[2] - $box[0];
    $h = $box[1] - $box[7];
    imagettftext($im, $pt, 0, (int) (($s - $w) / 2 - $box[0]), (int) (($s + $h) / 2 - $box[1] - $s * 0.04), $white, FONT, 'G');

    // The orange accent bar, echoing the drill arrow on the document.
    $barW = (int) ($s * 0.34);
    $barH = max(1, (int) ($s * 0.075));
    imagefilledrectangle(
        $im,
        (int) (($s - $barW) / 2),
        (int) ($s * 0.78),
        (int) (($s + $barW) / 2),
        (int) ($s * 0.78) + $barH,
        $orange,
    );

    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $im, 0, 0, 0, 0, $size, $size, $s, $s);
    imagedestroy($im);

    return $out;
}

function png(GdImage $im): string
{
    ob_start();
    imagepng($im, null, 9);

    return ob_get_clean();
}

/**
 * A PNG-in-ICO container. Every browser still in use reads these, and it keeps
 * the crisp downsample rather than re-encoding to a 256-colour BMP.
 *
 * @param  array<int, string>  $images  size => png bytes
 */
function ico(array $images): string
{
    $count = count($images);
    $out = pack('vvv', 0, 1, $count);
    $offset = 6 + (16 * $count);

    foreach ($images as $size => $data) {
        $out .= pack(
            'CCCCvvVV',
            $size >= 256 ? 0 : $size,   // 0 means 256 in the ICO header
            $size >= 256 ? 0 : $size,
            0, 0, 1, 32,
            strlen($data),
            $offset,
        );
        $offset += strlen($data);
    }

    return $out.implode('', $images);
}

$public = __DIR__.'/../public';

$icoSizes = [];
foreach ([16, 32, 48] as $size) {
    $im = tile($size);
    $icoSizes[$size] = png($im);
    imagedestroy($im);
}
file_put_contents("{$public}/favicon.ico", ico($icoSizes));

foreach ([32 => 'favicon-32.png', 180 => 'apple-touch-icon.png', 512 => 'icon-512.png'] as $size => $name) {
    $im = tile($size, rounded: $size < 512);
    file_put_contents("{$public}/{$name}", png($im));
    imagedestroy($im);
}

foreach (['favicon.ico', 'favicon-32.png', 'apple-touch-icon.png', 'icon-512.png'] as $name) {
    printf("%-22s %5d bytes\n", $name, filesize("{$public}/{$name}"));
}
