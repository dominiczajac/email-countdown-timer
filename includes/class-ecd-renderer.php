<?php
/** Image renderer. The v12.1 geometry is intentionally retained. */
if (!defined('ABSPATH')) exit;
final class Email_Countdown_Timer_Renderer {
    private array $boxes = [];
    private function bbox($size, $angle, $font, $text): array {
        $key = $font . "|" . $size . "|" . $text;
        if (!isset($this->boxes[$key])) {
            $box = @imagettfbbox($size, $angle, $font, $text);
            if ($box === false) throw new RuntimeException('Font metrics unavailable.');
            $this->boxes[$key] = $box;
        }
        return $this->boxes[$key];
    }
    public function render(array $c, int $deadline, int $now, string $format, ?array $ending = null): string {
        $this->boxes = [];
        $args = [$c['bg'], $c['dc'], $c['lc'], $c['font'], $c['size_digit'], $c['size_label'], (bool)$c['hide_days'],
            ['d'=>$c['label_d'], 'h'=>$c['label_h'], 'm'=>$c['label_m'], 's'=>$c['label_s']]];
        $image = $this->drawFrame(max(0, $deadline-$now), ...array_merge($args, [$c['fixed_width']]));
        $width = imagesx($image);
        $height = imagesy($image);
        $endFrame = $ending !== null ? Email_Countdown_Timer_End_Image::canvas($ending, $width, $height, $c['bg']) : null;
        if ($endFrame !== null && $now >= $deadline) return $this->encode($endFrame, $format);
        if ($format !== 'gif' || !class_exists('Imagick')) return $this->encode($image, $format);
        // Encode once, then reuse immutable bytes for the remaining end frames.
        $endBlob = $endFrame !== null ? $this->encode($endFrame, 'gif') : null;
        unset($endFrame);
        // Bound the aggregate decoded animation, not just an individual frame.
        if ($width * $height * 60 > 24000000) {
            unset($image);
            throw new RuntimeException('Animation exceeds the pixel budget.');
        }
        $animation = new Imagick();
        $optimized = null;
        try {
            $animation->setFormat('gif');
            for ($i=0; $i<60; $i++) {
                if ($endBlob !== null && $now + $i >= $deadline) {
                    $blob = $endBlob;
                } else {
                    // Reuse frame zero instead of drawing it a second time.
                    if ($i > 0) $image = $this->drawFrame(max(0, $deadline-$now-$i), ...array_merge($args, [$width, $height]));
                    $blob = $this->encode($image, 'gif');
                    unset($image);
                }
                $frame = new Imagick();
                try {
                    $frame->readImageBlob($blob);
                    $frame->setImageDelay(100);
                    $animation->addImage($frame);
                } finally { $frame->clear(); }
            }
            $optimized = $animation->optimizeImageLayers();
            // Imagick versions differ in whether they return the optimized sequence.
            return ($optimized instanceof Imagick ? $optimized : $animation)->getImagesBlob();
        } finally {
            if ($optimized instanceof Imagick && $optimized !== $animation) $optimized->clear();
            $animation->clear();
        }
    }
    /** Bounded current-time fallback: one frame in the requested image format. */
    public function render_static(array $c, int $deadline, int $now, string $format, ?array $ending = null): string {
        $this->boxes = [];
        $image = $this->drawFrame(max(0, $deadline - $now), $c['bg'], $c['dc'], $c['lc'], $c['font'],
            $c['size_digit'], $c['size_label'], (bool)$c['hide_days'],
            ['d'=>$c['label_d'], 'h'=>$c['label_h'], 'm'=>$c['label_m'], 's'=>$c['label_s']], $c['fixed_width']);
        if ($ending !== null && $now >= $deadline) {
            $endFrame = Email_Countdown_Timer_End_Image::canvas($ending, imagesx($image), imagesy($image), $c['bg']);
            if ($endFrame !== null) return $this->encode($endFrame, $format);
        }
        return $this->encode($image, $format);
    }
    private function encode($image, string $format): string {
        ob_start();
        try {
            if ($format === 'gif') $ok = imagegif($image);
            elseif ($format === 'webp') $ok = imagewebp($image, null, 90);
            else $ok = imagepng($image);
            $blob = (string)ob_get_contents();
            if (!$ok || $blob === '') throw new RuntimeException('Image encoding failed.');
            return $blob;
        } finally { ob_end_clean(); }
    }
    /** Measure the exact renderer layout without allocating or encoding an image. */
    public function measure(array $c, int $deadline, int $now): array {
        $layout = $this->layout(max(0, $deadline - $now), $c['bg'], $c['dc'], $c['lc'], $c['font'],
            $c['size_digit'], $c['size_label'], (bool)$c['hide_days'],
            ['d'=>$c['label_d'], 'h'=>$c['label_h'], 'm'=>$c['label_m'], 's'=>$c['label_s']], $c['fixed_width']);
        return ['width'=>(int)$layout['finalW'], 'height'=>(int)$layout['finalH'], 'font'=>$layout['fontPath']];
    }
    private function layout($remain, $bgHex, $dcHex, $lcHex, $fontFile, $sizeDigit, $sizeLabel, $hideDays, $labels, $forceW = 0, $forceH = null) {
        $d = intdiv($remain, 86400);
        $hr = intdiv($remain % 86400, 3600);
        $m = intdiv($remain % 3600, 60);
        $s = $remain % 60;

        $fontPath = Email_Countdown_Timer_Config::fontPath($fontFile);
        if (!function_exists('imagettfbbox') || !function_exists('imagettftext')) $fontPath = null;

        $gapX = 10; 
        $gapY = 10; 
        $padding = 10; 
        
        $digitAscent = 0;
        $digitHeight = 0;
        $singleDigitW = 0;
        $labelAscent = 0;
        $labelHeight = 0;
        $colonW = 0;

        if ($fontPath) {
            $boxD = $this->bbox($sizeDigit, 0, $fontPath, '8');
            $digitAscent = abs($boxD[7]); 
            $digitHeight = abs($boxD[7] - $boxD[1]); 
            
            $box0 = $this->bbox($sizeDigit, 0, $fontPath, '0');
            $w0 = abs($box0[2] - $box0[0]);
            $w8 = abs($boxD[2] - $boxD[0]);
            $singleDigitW = max($w0, $w8);

            $boxL = $this->bbox($sizeLabel, 0, $fontPath, 'M');
            $labelAscent = abs($boxL[7]);
            $labelHeight = abs($boxL[7] - $boxL[1]);

            $boxColon = $this->bbox($sizeDigit, 0, $fontPath, ':');
            $colonW = abs($boxColon[2] - $boxColon[0]);
        } else {
            $digitHeight = imagefontheight(5);
            $singleDigitW = imagefontwidth(5);
            $labelHeight = imagefontheight(3);
            $colonW = imagefontwidth(5);
            $gapY = 5;
        }

        $blocks = [];
        if (!($hideDays && $d == 0)) {
            $blocks[] = ['v' => $d, 'l' => $labels['d'], 'digits' => max(2, strlen((string)$d))];
        }
        $blocks[] = ['v' => $hr, 'l' => $labels['h'], 'digits' => 2];
        $blocks[] = ['v' => $m, 'l' => $labels['m'], 'digits' => 2];
        $blocks[] = ['v' => $s, 'l' => $labels['s'], 'digits' => 2];

        $meta = [];
        $totalContentW = 0;

        foreach ($blocks as $b) {
            $valStr = str_pad((string)$b['v'], 2, '0', STR_PAD_LEFT);
            $neededDigitW = $b['digits'] * $singleDigitW;
            
            if ($fontPath) {
                $boxLab = $this->bbox($sizeLabel, 0, $fontPath, $b['l']);
                $actualLabelW = abs($boxLab[2] - $boxLab[0]);
                
                $boxVal = $this->bbox($sizeDigit, 0, $fontPath, $valStr);
                $actualValW = abs($boxVal[2] - $boxVal[0]);
            } else {
                $actualLabelW = strlen($b['l']) * imagefontwidth(3);
                $actualValW = strlen($valStr) * imagefontwidth(5);
            }

            $colW = max($neededDigitW, $actualLabelW);
            $meta[] = [
                'v' => $valStr, 
                'l' => $b['l'], 
                'colW' => $colW,
                'realValW' => $actualValW,
                'realLabelW' => $actualLabelW
            ];
            $totalContentW += $colW;
        }

        $separatorsCount = count($blocks) - 1;
        $totalContentW += ($separatorsCount * (($gapX * 2) + $colonW));

        if ($forceW > 0) {
            $finalW = max($forceW, $totalContentW + ($padding * 2));
        } else {
            $finalW = $totalContentW + ($padding * 2);
        }
        
        $contentH = $digitHeight + $gapY + $labelHeight;
        $calculatedH = $contentH + ($padding * 2);
        $finalH = $forceH ?? $calculatedH;

        Email_Countdown_Timer_Config::checkCanvas((int)$finalW, (int)$finalH);
        return compact('fontPath', 'gapX', 'gapY', 'padding', 'digitAscent', 'digitHeight', 'labelAscent', 'colonW', 'finalW', 'finalH', 'totalContentW', 'meta');
    }
    public function drawFrame($remain, $bgHex, $dcHex, $lcHex, $fontFile, $sizeDigit, $sizeLabel, $hideDays, $labels, $forceW = 0, $forceH = null) {
        $layout = $this->layout($remain, $bgHex, $dcHex, $lcHex, $fontFile, $sizeDigit, $sizeLabel, $hideDays, $labels, $forceW, $forceH);
        ['fontPath'=>$fontPath, 'gapX'=>$gapX, 'gapY'=>$gapY, 'padding'=>$padding, 'digitAscent'=>$digitAscent, 'digitHeight'=>$digitHeight, 'labelAscent'=>$labelAscent, 'colonW'=>$colonW, 'finalW'=>$finalW, 'finalH'=>$finalH, 'totalContentW'=>$totalContentW, 'meta'=>$meta] = $layout;
        $im = imagecreatetruecolor((int)$finalW, (int)$finalH);
        $bg = $this->allocHex($im, $bgHex);
        imagefill($im, 0, 0, $bg);
        $dc = $this->allocHex($im, $dcHex);
        $lc = $this->allocHex($im, $lcHex);

        $startX = ($finalW - $totalContentW) / 2;
        $currX = $startX;

        if ($fontPath) {
            $baseY_Digit = $padding + $digitAscent;
            $baseY_Label = $padding + $digitHeight + $gapY + $labelAscent;
        } else {
            $yDigit = $padding;
            $yLabel = $padding + $digitHeight + $gapY;
        }

        foreach ($meta as $index => $b) {
            $offV = ($b['colW'] - $b['realValW']) / 2;
            $offL = ($b['colW'] - $b['realLabelW']) / 2;

            if ($fontPath) {
                imagettftext($im, $sizeDigit, 0, (int)($currX + $offV), (int)$baseY_Digit, $dc, $fontPath, $b['v']);
                imagettftext($im, $sizeLabel, 0, (int)($currX + $offL), (int)$baseY_Label, $lc, $fontPath, $b['l']);
            } else {
                imagestring($im, 5, (int)($currX + $offV), (int)$yDigit, $b['v'], $dc);
                imagestring($im, 3, (int)($currX + $offL), (int)$yLabel, $b['l'], $lc);
            }
            
            $currX += $b['colW'];

            if ($index < count($meta) - 1) {
                $currX += $gapX;
                if ($fontPath) {
                    imagettftext($im, $sizeDigit, 0, (int)$currX, (int)$baseY_Digit, $dc, $fontPath, ':');
                } else {
                    imagestring($im, 5, (int)$currX, (int)$yDigit, ':', $dc);
                }
                $currX += $colonW + $gapX;
            }
        }

        return $im;
    }

    private function allocHex($im, $hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) == 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        return imagecolorallocate($im, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }

}
