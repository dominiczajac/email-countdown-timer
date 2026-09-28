<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Test-only layout oracle from the user-provided v12.1 renderer. */
class ECD_Legacy_Frame {
    public function drawFrame($remain, $bgHex, $dcHex, $lcHex, $fontFile, $sizeDigit, $sizeLabel, $hideDays, $labels, $forceW = 0, $forceH = null) {
        $d = intdiv($remain, 86400);
        $hr = intdiv($remain % 86400, 3600);
        $m = intdiv($remain % 3600, 60);
        $s = $remain % 60;

        $fontPath = $fontFile ? ECD_PLUGIN_DIR . 'fonts/' . $fontFile : null;
        if ($fontPath && !file_exists($fontPath)) $fontPath = null;

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
            $boxD = imagettfbbox($sizeDigit, 0, $fontPath, '8');
            $digitAscent = abs($boxD[7]); 
            $digitHeight = abs($boxD[7] - $boxD[1]); 
            
            $box0 = imagettfbbox($sizeDigit, 0, $fontPath, '0');
            $w0 = abs($box0[2] - $box0[0]);
            $w8 = abs($boxD[2] - $boxD[0]);
            $singleDigitW = max($w0, $w8);

            $boxL = imagettfbbox($sizeLabel, 0, $fontPath, 'M');
            $labelAscent = abs($boxL[7]);
            $labelHeight = abs($boxL[7] - $boxL[1]);

            $boxColon = imagettfbbox($sizeDigit, 0, $fontPath, ':');
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
                $boxLab = imagettfbbox($sizeLabel, 0, $fontPath, $b['l']);
                $actualLabelW = abs($boxLab[2] - $boxLab[0]);
                
                $boxVal = imagettfbbox($sizeDigit, 0, $fontPath, $valStr);
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
