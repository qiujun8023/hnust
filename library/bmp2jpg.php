<?php
function bmp2gd($src,$dest = false) {
    if (!($src_f = fopen($src, "rb"))) {
        return false;
    }
    if (!($dest_f = fopen($dest, "wb"))) {
        return false;
    }
    /*** grab the header ***/
    $header = unpack("vtype/Vsize/v2reserved/Voffset", fread( $src_f, 14));

    /*** grab the rest of the image ***/
    $info = unpack("Vsize/Vwidth/Vheight/vplanes/vbits/Vcompression/Vimagesize/Vxres/Vyres/Vncolor/Vimportant", fread($src_f, 40));

    /*** extract the header and info into varibles ***/
    extract($info);
    extract($header);

    /*** check for BMP signature ***/
    if ($type != 0x4D42) {
        return false;
    }

    /*** set the pallete ***/
    $palette_size = $offset - 54;
    $ncolor = $palette_size / 4;
    $gd_header = "";

    /*** true-color vs. palette ***/
    $gd_header .= ($palette_size == 0) ? "\xFF\xFE" : "\xFF\xFF";
    $gd_header .= pack("n2", $width, $height);
    $gd_header .= ($palette_size == 0) ? "\x01" : "\x00";
    if($palette_size) {
        $gd_header .= pack("n", $ncolor);
    }
    /*** we do not allow transparency ***/
    $gd_header .= "\xFF\xFF\xFF\xFF";

    /*** write the destination headers ***/
   fwrite($dest_f, $gd_header);

    /*** if we have a valid palette ***/
    if($palette_size) {
        /*** read the palette ***/
        $palette = fread($src_f, $palette_size);
        /*** begin the gd palette ***/
        $gd_palette = "";
        $j = 0;
        /*** loop of the palette ***/
        while($j < $palette_size) {
            $b = $palette{$j++};
            $g = $palette{$j++};
            $r = $palette{$j++};
            $a = $palette{$j++};
            /*** assemble the gd palette ***/
            $gd_palette .= "$r$g$b$a";
        }
        /*** finish the palette ***/
        $gd_palette .= str_repeat("\x00\x00\x00\x00", 256 - $ncolor);
        /*** write the gd palette ***/
        fwrite($dest_f, $gd_palette);
    }

    /*** scan line size and alignment ***/
    $scan_line_size = (($bits * $width) + 7) >> 3;
    $scan_line_align = ($scan_line_size & 0x03) ? 4 - ($scan_line_size & 0x03) : 0;

    /*** this is where the work is done ***/
    for($i = 0, $l = $height - 1; $i < $height; $i++, $l--) {
        /*** create scan lines starting from bottom ***/
        fseek($src_f, $offset + (($scan_line_size + $scan_line_align) * $l));
        $scan_line = fread($src_f, $scan_line_size);
        if($bits == 24) {
            $gd_scan_line = "";
            $j = 0;
            while($j < $scan_line_size) {
                $b = $scan_line{$j++};
                $g = $scan_line{$j++};
                $r = $scan_line{$j++};
                $gd_scan_line .= "\x00$r$g$b";
            }
        } else if($bits == 8) {
            $gd_scan_line = $scan_line;
        } else if($bits == 4) {
            $gd_scan_line = "";
            $j = 0;
            while($j < $scan_line_size) {
                $byte = ord($scan_line{$j++});
                $p1 = chr($byte >> 4);
                $p2 = chr($byte & 0x0F);
                $gd_scan_line .= "$p1$p2";
            }
            $gd_scan_line = substr($gd_scan_line, 0, $width);
        } else if($bits == 1) {
            $gd_scan_line = "";
            $j = 0;
            while($j < $scan_line_size) {
                $byte = ord($scan_line{$j++});
                $p1 = chr((int) (($byte & 0x80) != 0));
                $p2 = chr((int) (($byte & 0x40) != 0));
                $p3 = chr((int) (($byte & 0x20) != 0));
                $p4 = chr((int) (($byte & 0x10) != 0));
                $p5 = chr((int) (($byte & 0x08) != 0));
                $p6 = chr((int) (($byte & 0x04) != 0));
                $p7 = chr((int) (($byte & 0x02) != 0));
                $p8 = chr((int) (($byte & 0x01) != 0));
                $gd_scan_line .= "$p1$p2$p3$p4$p5$p6$p7$p8";
            }
            /*** put the gd scan lines together ***/
            $gd_scan_line = substr($gd_scan_line, 0, $width);
        }
        /*** write the gd scan lines ***/
        fwrite($dest_f, $gd_scan_line);
    }
    /*** close the source file ***/
    fclose($src_f);
    /*** close the destination file ***/
    fclose($dest_f);

    return true;
}

function bmp2jpg($imgPath) {
    $tmpPath=tempnam(SAE_TMP_PATH,"img");
    if(bmp2gd($imgPath, $tmpPath)) {
        $img = imagecreatefromgd($tmpPath);
        imagejpeg($img, $imgPath);
        unlink($tmpPath);
        return true;
    }
    return false;
}