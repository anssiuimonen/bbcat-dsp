<?php

  /*--------------------------------------------------------------------------------*/
  /** This PHP script generates the SoundFormatRawConversions.cpp code
   */
  /*--------------------------------------------------------------------------------*/

$endians = array('LE',
                 'BE');

$testendian = array("#if __BYTE_ORDER__ == __ORDER_LITTLE_ENDIAN__\n",
                    "#if __BYTE_ORDER__ == __ORDER_BIG_ENDIAN__\n");

$formats = array('',
                 '16bit',
                 '24bit',
                 '32bit',
                 'Float',
                 'Double');

$sizes   = array(0, 2, 3, 4, 4, 8);

$types   = array('',
                 'sint32_t',
                 'sint32_t',
                 'sint32_t',
                 'float',
                 'double');

$utypes  = array('',
                 'uint32_t',
                 'uint32_t',
                 'uint32_t',
                 'uint32_t',
                 'uint64_t');

$rawtypes = array('',
                  'sint16_t',
                  '',
                  'sint32_t',
                  'float',
                  'double');

$typesize = array('sint16_t' => 2,
                  'uint16_t' => 2,
                  'sint32_t' => 4,
                  'uint32_t' => 4,
                  'float'    => 4,
                  'uint64_t' => 8,
                  'double'   => 8);

$args = "(const uint8_t *src, uint8_t *dst, uint_t nchannels, uint_t nframes, sint_t srcflen, sint_t dstflen, Ditherer *ditherer)";

// set this to false to disable statements such as sval = *(const float *)src which MAY end up by misaligned accesses and therefore
// MAY crash certain processors (ARMs for example).  The resultant code will be larger and probably slower as well
$allow_direct = true;

if ($fp = fopen("SoundFormatRawConversions.cpp", "w")) {
  fprintf($fp, "// This code is auto-generated by genconversions.php -> DO NOT EDIT!!\n");
  fprintf($fp, "//\n");
  fprintf($fp, "// Don't call these functions directly!  Use TransferSamples(), see SoundFormatConversions.h\n\n");

  fprintf($fp, "#include <stdio.h>\n");
  fprintf($fp, "#include <stdlib.h>\n");
  fprintf($fp, "#include <string.h>\n");
  fprintf($fp, "#include <math.h>\n");

  fprintf($fp, "\n");

  fprintf($fp, "#define DEBUG_LEVEL 1\n");
  fprintf($fp, "#include \"SoundFormatRawConversions.h\"\n");

  fprintf($fp, "\n");

  fprintf($fp, "BBC_AUDIOTOOLBOX_START\n");
  fprintf($fp, "\n");

  fprintf($fp, "// macro to reduce the ugliness of using static_cast\n");
  fprintf($fp, "#define cast(type,val) static_cast<type>(val)\n");
  fprintf($fp, "\n");

  fprintf($fp, "// macro to directly access memory via the specified type\n");
  fprintf($fp, "#define mem(type,var)       (*(type *)(var))\n");
  fprintf($fp, "#define const_mem(type,var) (*(const type *)(var))\n");
  fprintf($fp, "\n");

  /*--------------------------------------------------------------------------------*/
  /** Stage 1: generate bulk copy functions
   *
   * These are simply memcpys for different sample sizes
   */
  /*--------------------------------------------------------------------------------*/
  $sizesdone = array(0 => true);        // keep track of sizes already done (since some sizes appear twice in the list) - make sure zero memory copy function is not generated!
  foreach ($sizes as $size) {
    if (!isset($sizesdone[$size])) {
      $sizesdone[$size] = true; // stop function being created more than once

      fprintf($fp, "static void __CopyMemory_" . $size . $args . "\n");
      fprintf($fp, "{\n");
      fprintf($fp, "  uint_t i;\n");
      fprintf($fp, "\n");
      fprintf($fp, "  (void)ditherer;");
      fprintf($fp, "\n");
      fprintf($fp, "  for (i = 0; i < nframes; i++, src += srcflen, dst += dstflen)\n  {\n");
      fprintf($fp, "    if (dst != src) memcpy(dst, src, nchannels * $size);\n");
      fprintf($fp, "  }\n");
      fprintf($fp, "}\n");
      fprintf($fp, "\n");
    }
  }

  /*--------------------------------------------------------------------------------*/
  /** Stage 2: generate conversion functions
   */
  /*--------------------------------------------------------------------------------*/
  for ($src_be = 0; $src_be < count($endians); $src_be++) {
    for ($dst_be = 0; $dst_be < count($endians); $dst_be++) {
      for ($src_fmt = 0; $src_fmt < count($formats); $src_fmt++) {
        $srctype  = $types[$src_fmt];
        $srcutype = $utypes[$src_fmt];
        $srcsize  = $sizes[$src_fmt];

        for ($dst_fmt = 0; $dst_fmt < count($formats); $dst_fmt++) {
          $dsttype  = $types[$dst_fmt];
          $dstutype = $utypes[$dst_fmt];
          $dstsize  = $sizes[$dst_fmt];

          if ((($src_be != $dst_be) || ($src_fmt != $dst_fmt)) &&
              ($srcsize > 0) && ($dstsize > 0)) {
            fprintf($fp, "/*--------------------------------------------------------------------------------*/\n");
            fprintf($fp, "/** Convert " . $formats[$src_fmt] . " (" . $endians[$src_be] . ") samples to " . $formats[$dst_fmt] . " (" . $endians[$dst_be] . ") samples\n");
            fprintf($fp, " */\n");
            fprintf($fp, "/*--------------------------------------------------------------------------------*/\n");
            fprintf($fp, "static void __Convert_" . $formats[$src_fmt] . $endians[$src_be] . "_to_" . $formats[$dst_fmt] . $endians[$dst_be] . $args . "\n");
            fprintf($fp, "{\n");
            if (($srctype[0] == 's') && ($dsttype[0] != 's')) {
              fprintf($fp, "  static const $dsttype factor = cast($dsttype, pow(2.0, -31.0));\n");
            }
            else if (($srctype[0] != 's') && ($dsttype[0] == 's')) {
              fprintf($fp, "  static const $srctype factor = cast($srctype, pow(2.0, 31.0));\n");
            }

            fprintf($fp, "  " . str_pad($srctype,  9, ' ', STR_PAD_RIGHT) . "sval;\n");
            fprintf($fp, "  " . str_pad($dsttype,  9, ' ', STR_PAD_RIGHT) . "dval;\n");
            fprintf($fp, "  " . str_pad($srcutype, 9, ' ', STR_PAD_RIGHT) . "*svp = ($srcutype *)&sval;\n");
            fprintf($fp, "  " . str_pad($dstutype, 9, ' ', STR_PAD_RIGHT) . "*dvp = ($dstutype *)&dval;\n");
            fprintf($fp, "  uint_t i, j;\n\n");

            fprintf($fp, "  (void)svp;\n");
            fprintf($fp, "  (void)dvp;\n");
            fprintf($fp, "\n");
            fprintf($fp, "  (void)ditherer;");
            fprintf($fp, "\n");

            if ($src_fmt < $dst_fmt) {
              fprintf($fp, "  // destination samples are bigger -> start from end of frame and work backwards\n");
              fprintf($fp, "  src += nchannels * " . $srcsize . " - " . $srcsize . ";\n");
              fprintf($fp, "  dst += nchannels * " . $dstsize . " - " . $dstsize . ";\n");
              fprintf($fp, "\n");
              $inc = '-=';
              $dec = '+=';
            }
            else {
              $inc = '+=';
              $dec = '-=';
            }

            fprintf($fp, "  // adjust frame increments for effects of for-loop\n");
            fprintf($fp, "  srcflen $dec nchannels * " . $srcsize . ";\n");
            fprintf($fp, "  dstflen $dec nchannels * " . $dstsize . ";\n");
            fprintf($fp, "\n");

            fprintf($fp, "  // process each frame\n");
            fprintf($fp, "  for (i = 0; i < nframes; i++, src += srcflen, dst += dstflen)\n  {\n");

            fprintf($fp, "    // process each channel\n");
            fprintf($fp, "    for (j = 0; j < nchannels; j++, src $inc " . $srcsize . ", dst $inc " . $dstsize . ")\n    {\n");

            $needendif = $allow_direct;
            if ($srctype[0] == 's') {
              if ($allow_direct && ($srcsize == $typesize[$srctype])) {
                fprintf($fp, $testendian[$src_be]);
                fprintf($fp, "      // read integer sample directly\n");
                fprintf($fp, "      sval = const_mem($srctype, src);\n");
                fprintf($fp, "#else\n");
              }
              else if ($allow_direct && ($srcsize == 2)) {
                fprintf($fp, $testendian[$src_be]);
                fprintf($fp, "      // read 16-bit sample and convert to 32-bit sample (note use of unsigned arithmetic to avoid problems with left shift)\n");
                fprintf($fp, "      sval = cast(uint32_t, const_mem(sint16_t, src) << 16);\n");
                fprintf($fp, "#else\n");
              }
              else $needendif = false;
              fprintf($fp, "      // read integer bytes representing integer sample (note use of unsigned arithmetic to avoid problems with left shift)\n");
              fprintf($fp, "      sval = ");
            }
            else {
              if ($allow_direct) {
                fprintf($fp, $testendian[$src_be]);
                fprintf($fp, "      // read floating point sample directly\n");
                fprintf($fp, "      sval = const_mem($srctype, src);\n");
                fprintf($fp, "#else\n");
              }
              fprintf($fp, "      // read integer bytes representing floating point sample (note use of unsigned arithmetic to avoid problems with left shift)\n");
              fprintf($fp, "      svp[0] = ");
            }

            for ($i = 0; $i < $srcsize; $i++) {
              if ($i > 0) fprintf($fp, " + ");
              fprintf($fp, "(cast($srcutype, src[" . (($src_be == 0) ? ($srcsize - 1 - $i) : $i) . "])");
              if ($i < ($typesize[$srctype] - 1)) fprintf($fp, " << " . (8 * ($typesize[$srctype] - 1 - $i)));
              fprintf($fp, ")");
            }

            fprintf($fp, ";\n");

            if ($needendif) fprintf($fp, "#endif\n");

            if (($dsttype[0] == 's') && ($dstsize < $srcsize)) {
              fprintf($fp, "      // apply dither\n");
              fprintf($fp, "      if (ditherer) ditherer->Dither(i, sval, " . (($typesize[$dsttype] - $dstsize) * 8) . ");\n");
            }

            $needendif = $allow_direct;
            if (($srctype[0] == 's') && ($dsttype[0] == 's')) {
              if ($allow_direct && ($dstsize == $typesize[$dsttype])) {
                fprintf($fp, $testendian[$dst_be]);
                fprintf($fp, "      // write integer sample directly\n");
                fprintf($fp, "      mem($dsttype, dst) = sval;\n");
                fprintf($fp, "#else\n");
              }
              else if ($allow_direct && ($dstsize == 2)) {
                fprintf($fp, $testendian[$dst_be]);
                fprintf($fp, "      // write 16-bit integer sample directly\n");
                fprintf($fp, "      mem(sint16_t, dst) = cast(sint16_t, sval >> 16);\n");
                fprintf($fp, "#else\n");
              }
              else $needendif = false;
              $var = "sval";
            }
            else if ($srctype == $dsttype) {
              if ($allow_direct) {
                fprintf($fp, $testendian[$dst_be]);
                fprintf($fp, "      // write floating point sample directly\n");
                fprintf($fp, "      mem($dsttype, dst) = sval;\n");
                fprintf($fp, "#else\n");
              }
              $var = "svp[0]";
            }
            else if (($srctype[0] == 's') && ($dsttype[0] != 's')) {
              fprintf($fp, "      // convert integer sample to floating point sample (scale)\n");
              fprintf($fp, "      dval = cast($dsttype, sval) * factor;\n");
              if ($allow_direct) {
                fprintf($fp, $testendian[$dst_be]);
                fprintf($fp, "      // write floating point sample directly\n");
                fprintf($fp, "      mem($dsttype, dst) = dval;\n");
                fprintf($fp, "#else\n");
              }
              $var = "dvp[0]";
            }
            else if (($srctype[0] != 's') && ($dsttype[0] == 's')) {
              fprintf($fp, "      // convert floating point sample to integer sample (scale and limit)\n");
              fprintf($fp, "      dval = cast($dsttype, LIMIT(sval * factor, -2147483648.0, 2147483647.0));\n");
              if ($allow_direct && ($dstsize == $typesize[$dsttype])) {
                fprintf($fp, $testendian[$dst_be]);
                fprintf($fp, "      // write integer sample directly\n");
                fprintf($fp, "      mem($dsttype, dst) = dval;\n");
                fprintf($fp, "#else\n");
              }
              else if ($allow_direct && ($dstsize == 2)) {
                fprintf($fp, $testendian[$dst_be]);
                fprintf($fp, "      // write 16-bit integer sample directly\n");
                fprintf($fp, "      mem(sint16_t, dst) = cast(sint16_t, dval >> 16);\n");
                fprintf($fp, "#else\n");
              }
              else $needendif = false;
              $var = "dvp[0]";
            }
            else {
              fprintf($fp, "      // convert one type of floating point sample to another\n");
              fprintf($fp, "      dval = cast($dsttype, sval);\n");
              if ($allow_direct) {
                fprintf($fp, $testendian[$dst_be]);
                fprintf($fp, "      // write floating point sample directly\n");
                fprintf($fp, "      mem($dsttype, dst) = dval;\n");
                fprintf($fp, "#else\n");
              }
              $var = "dvp[0]";
            }

            fprintf($fp, "      // write sample bytes to destination\n");
            for ($i = 0; $i < $dstsize; $i++) {
              fprintf($fp, "      dst[" . (($dst_be == 0) ? ($dstsize - 1 - $i) : $i) . "] = cast(uint8_t, $var");
              if ($i < ($typesize[$dsttype] - 1)) fprintf($fp, " >> " . (8 * ($typesize[$dsttype] - 1 - $i)));
              fprintf($fp, ");\n");
            }

            if ($needendif) fprintf($fp, "#endif\n");

            fprintf($fp, "    }\n");
            fprintf($fp, "  }\n");
            fprintf($fp, "}\n\n");
          }
        }
      }
    }
  }

  /*--------------------------------------------------------------------------------*/
  /** Stage 3: generate function lookups
   *
   */
  /*--------------------------------------------------------------------------------*/

  fprintf($fp, "const CONVERTSAMPLES SoundFormatConversions[2][2][SampleFormat_Count][SampleFormat_Count] =\n{\n");
  for ($src_be = 0; $src_be < count($endians); $src_be++) {
    fprintf($fp, "  {\n");
    for ($dst_be = 0; $dst_be < count($endians); $dst_be++) {
      fprintf($fp, "    {\n");
      fprintf($fp, "      // " . $endians[$src_be] . " -> " . $endians[$dst_be] . "\n");
      for ($src_fmt = 0; $src_fmt < count($formats); $src_fmt++) {
        fprintf($fp, "      {\n");
        for ($dst_fmt = 0; $dst_fmt < count($formats); $dst_fmt++) {
          fprintf($fp, "        // " . $formats[$src_fmt] . " (" . $endians[$src_be] . ") -> " . $formats[$dst_fmt] . " (" . $endians[$dst_be] . ")\n");
          fprintf($fp, "        ");
          if (($sizes[$src_fmt] == 0) || ($sizes[$dst_fmt] == 0)) {
            fprintf($fp, "NULL /* no valid conversion */");
          }
          else if (($src_be == $dst_be) && ($src_fmt == $dst_fmt)) {
            fprintf($fp, "&__CopyMemory_" . $sizes[$src_fmt]);
          }
          else fprintf($fp, "&__Convert_" . $formats[$src_fmt] . $endians[$src_be] . "_to_" . $formats[$dst_fmt] . $endians[$dst_be]);
          fprintf($fp, ",\n");
        }
        fprintf($fp, "      },\n");
      }
      fprintf($fp, "    },\n");
    }
    fprintf($fp, "  },\n");
  }
  fprintf($fp, "};\n");

  fprintf($fp, "\n");
  fprintf($fp, "BBC_AUDIOTOOLBOX_END\n");

  fclose($fp);
 }

// this doesn't need to be run every time
if (false) {
  // output function prototypes for combinations of source and destination types assuming in memory transfers (i.e. endianness is the same as the machine's endianness)
  for ($src_fmt = 0; $src_fmt < count($formats); $src_fmt++) {
    $srctype   = $rawtypes[$src_fmt];
    $srcformat = 'SampleFormat_' . $formats[$src_fmt];

    for ($dst_fmt = 0; $dst_fmt < count($formats); $dst_fmt++) {
      $dsttype   = $rawtypes[$dst_fmt];
      $dstformat = 'SampleFormat_' . $formats[$dst_fmt];

      if (($srctype != '') && ($dsttype != '')) {
        echo "extern void TransferSamples(const $srctype *src, uint_t src_channel, uint_t src_channels, $dsttype *dst, uint_t dst_channel, uint_t dst_channels, uint_t nchannels = ~0, uint_t nframes = 1, Ditherer *ditherer = NULL);\n";
      }
    }
  }

  echo "\n";

  // output function bodies
  for ($src_fmt = 0; $src_fmt < count($formats); $src_fmt++) {
    $srctype   = $rawtypes[$src_fmt];
    $srcformat = 'SampleFormat_' . $formats[$src_fmt];

    for ($dst_fmt = 0; $dst_fmt < count($formats); $dst_fmt++) {
      $dsttype   = $rawtypes[$dst_fmt];
      $dstformat = 'SampleFormat_' . $formats[$dst_fmt];

      if (($srctype != '') && ($dsttype != '')) {
        echo "void TransferSamples(const $srctype *src, uint_t src_channel, uint_t src_channels, $dsttype *dst, uint_t dst_channel, uint_t dst_channels, uint_t nchannels, uint_t nframes, Ditherer *ditherer)\n";
        echo "{\n";
        echo "  TransferSamples(src, $srcformat, MACHINE_IS_BIG_ENDIAN, src_channel, src_channels, dst, $dstformat, MACHINE_IS_BIG_ENDIAN, dst_channel, dst_channels, nchannels, nframes, ditherer);\n";
        echo "}\n";
        echo "\n";
      }
    }
  }
}

?>
