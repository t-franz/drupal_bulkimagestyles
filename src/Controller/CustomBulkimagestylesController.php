<?php

namespace Drupal\custom_bulkimagestyles\Controller;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Render\Markup;
use Drupal\breakpoint\BreakpointManagerInterface;


/**
 * Returns responses for Bulk Image Styles routes.
 */
class CustomBulkimagestylesController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {


    $settingsForm = $this->formBuilder()->getForm('Drupal\custom_bulkimagestyles\Form\SettingsForm');
    $renderer = \Drupal::service('renderer');
    $settingsFormHtml = $renderer->render($settingsForm);

    $build['content'] = [
        '#markup' => Markup::create("
            {$settingsFormHtml}
        ")
    ];

    return $build;
  }

    /**
   * Builds the response.
   * Save configs:  https://gist.github.com/mikecrittenden/2d2c6734c506d509505fa79142125757
   */
  public function generate() {

    //https://www.drupal.org/docs/drupal-apis/entity-api/working-with-the-entity-api

/*  $entity = \Drupal::entityTypeManager()->getStorage('image_style');
    $query = $entity->getQuery();
    $ids = $query->execute(); */

    /* $uuid_service = \Drupal::service('uuid');
    $newname = 'new6';
    $properties = [
      'name' =>$newname,
      'label' =>$newname,
      'effects' => [
        $uuid_service->generate() => [
          'uuid' => $uuid_service->generate(),
          'id' =>'image_style_quality',
          'weight' => '2',
          'data' => [
            'quality' => '25',
          ],
        ]
      ]
    ];
    $newstyle = \Drupal::entityTypeManager()->getStorage('image_style')->create($properties);
    $newstyle->save(); */


    /** @var \Drupal\Core\Config\StorageInterface $active_storage */
    $config_storage = \Drupal::service('config.storage');
    $moduleHandler = \Drupal::service('module_handler');

    $markup = '<h3>Generated image styles:</h3><p>';

    $styleName = $_GET['name'];
    $styleNameLower = str_replace(' ','_',strtolower($styleName));
    $responsiveStyleMachineName = strtolower($_GET['responsive_style']);
    $responsiveStyleLabel = str_replace('_',' ',ucfirst($responsiveStyleMachineName));
    $breakpoint_group = $_GET['breakpoint_group'];
    $scale = $_GET['scale'];
    $rationame = $_GET['ratio'];
    $crop_type = ($moduleHandler->moduleExists('image_widget_crop')) ? $_GET['crop_type'] : '';
    $ratio = explode(':',str_replace(' ', '',$rationame));
    $widths = explode(",", str_replace(' ', '',$_GET['widths']));
    $mediaQuery = $_GET['query'];
    $highquality = ($moduleHandler->moduleExists('image_style_quality')) ? $_GET['highquality'] : '';
    $lowquality = ($moduleHandler->moduleExists('image_style_quality')) ? $_GET['lowquality'] : '';
    $buildResponsiveStyle = !empty($responsiveStyleLabel);


    if (strpos($responsiveStyleMachineName,'banner')) {
      \Drupal::messenger()->addMessage("Edit Banner? Don't forget to edit layout.css for changes in aspect ratio.");
    }

/*     $breakpoints = \Drupal::service('breakpoint.manager')->getBreakpointsByGroup($breakpoint_group);
    foreach ($breakpoints as $breakpoint_id => $breakpoint) {
      foreach ($breakpoint->getMultipliers() as $multiplier) {
        // $breakpoint_id
        // $multiplier
        // $breakpoint->getLabel()
        // $breakpoint->getMediaQuery()

        preg_match('/\d+/',$multiplier,$multiply);

        $regex = '/min-width:.(.+?)px/m';
        $query = $breakpoint->getMediaQuery();
        preg_match($regex, $query, $matches);
        $foundwidth = intval($matches[1]) * intval($multiply[0]);
        $width[] = $foundwidth;
      }
    } */

    $styleQuality = [[1,$highquality]];

    $styleRatio = 1;
    $ratioMachineName = '';

    if (isset($ratio[1])) {
      $styleRatio = $ratio[1]/$ratio[0];
      if (is_int($ratio[0]/$ratio[1])) {
        $gcd = gcd($ratio[0],$ratio[1]);
        $ratio[0] = $ratio[0]/$gcd;
        $ratio[1] = $ratio[1]/$gcd;
      }
      $rationame = $ratio[0].':'.$ratio[1].' ';
      $ratioMachineName = $ratio[0].'_'.$ratio[1].'__';
    }


    $lowquality ? $styleQuality[] = [2,$lowquality] : '';

    $allStyles = [];
    $allConfigs = [];

    $fallbackLabel = null;

    $single = !isset($widths[1]);

    $widths[] = end($widths);
    $keyed = array_keys($widths);
    $last_element = end($keyed);

    foreach ($widths as $key => $width) {
      $fallback = ($key == $last_element);

      foreach ($styleQuality as $resolution => $quality) {
        $resolution++;

        $qualityMachineLabel = '';
        $qualityLabel = '';
        $resolutionMachineLabel = '';
        $resolutionLabel = '';

        if ($resolution > 1) {
          $resolutionMachineLabel = '_'.$resolution.'x';
          $resolutionLabel = ' '.$resolution.'x';
        }


        if ($fallback) {
          $qualityMachineLabel = '_jpeg';
          $qualityLabel .= ' (JPEG)';
        }

        if (!empty($styleName)) {
          $ratioMachineName = $styleNameLower.'_';
          $rationame = $styleName.' ';
        }

        $styleMachineName = $ratioMachineName.$width.'w'.$resolutionMachineLabel.$qualityMachineLabel;
        $label = $rationame.$width.'w '.$resolutionLabel.$qualityLabel;

        $configName = 'image.style.'.$styleMachineName;

        if($scale == 'image_scale') {
          $dataSettings = [
            'width'  => intval(round($width*$resolution)),
            'height' => null,
            'upscale' => false,
          ];
        } else {
          $dataSettings = [
            'width'  => intval(round($width*$resolution)),
            'height' => intval(round($width*$styleRatio*$resolution)),
            'anchor' => 'center-center',
          ];
        }

        $uuid_service = \Drupal::service('uuid');

        $effects = [];

        $effects[0] = [
          'uuid' => $uuid_service->generate(),
            'id' => $scale,
            'weight' => 1,
            'data' => $dataSettings,
          ];

        if (!$fallback) {
          $allStyles[$quality[0]][] = $styleMachineName;
          $allConfigs[] = $configName;
          $effects[] = [
              'uuid'=> $uuid_service->generate(),
              'id'=> 'image_convert',
              'weight'=> -9,
              'data'=> ['extension' => 'webp'],
          ];
        }

        if (!empty($crop_type)) {
          $effects[] = [
            'uuid'=> $uuid_service->generate(),
            'id'=> 'crop_crop',
            'weight'=> -10,
            'data'=> ['crop_type' => $crop_type],
          ];
        }

        $configData = array(
          'langcode' => 'de',
          'status' => 1,
          'dependencies' => array(),
          'name' => $styleMachineName,
          'label' => $label,
          'effects' => $effects
        );

        if ($highquality) {
          $configData['effects'][0] = [
              'uuid' => $uuid_service->generate(),
              'id' => 'image_style_quality',
              'weight' => 2,
              'data' => array(
                'quality' => $quality[1]
              ),
          ];
        }

        $config_storage->write($configName, $configData);

        $markup .= '<a href="/admin/config/media/image-styles/manage/'.$styleMachineName.'" target="_blank">'.$label.' ('.$styleMachineName.')</a><br>';

        if ($fallback) {
          $fallbackLabel = $styleMachineName;
          break;
        }
      }
    }

    if (isset($ratio[1]) && $scale == 'image_scale_and_crop') {
      $markup .= '<br><b>layout.css</b><br><pre><code>.aspect-ratio-'.$ratio[0].'_'.$ratio[1].' {
        aspect-ratio: '.$ratio[0].' / '.$ratio[1].';
      }</code></pre>';
    }

    /* Responsive ImageStyle
    * 1. Export Single Config
    * 2. Convert to PHP-Array:
    * https://wtools.io/convert-yaml-to-php-array
    */


    !isset($allStyles[2]) ? $allStyles[2] = [] : '';

    if ($buildResponsiveStyle) {

      $multipleSizes = (!empty($allStyles[1][1]));
      $multipleConfigs = (count($allConfigs)>1);


      if ($multipleSizes) {
        $image_mapping = [
          'sizes' => $mediaQuery,
          'sizes_image_styles' => $allStyles[1],
        ];
      } else {
        $image_mapping = $allStyles[1][0];
      }

      $image_style_mappings = ['image_style_mappings' => [
        0 => ['breakpoint_id' => 'custom_bulkimagestyles.devicewidth.min320',
          'multiplier' => '1x',
          'image_mapping_type' => $multipleSizes ? 'sizes' : 'image_style',
          'image_mapping' => $image_mapping
          ]]];

      $dependencies = ['config' => $allConfigs];

      if ($multipleConfigs) {
        if ($multipleSizes) {
          $image_mapping = [
            'sizes' => $mediaQuery,
            'sizes_image_styles' => $allStyles[2],
          ];
        } else {
          $image_mapping = $allStyles[2][0];
        }
        $dependencies['theme'] = [0 => 'fus'];
        $image_style_mappings['image_style_mappings'][] = [
          'breakpoint_id' => 'custom_bulkimagestyles.devicewidth.min320_2x',
            'multiplier' => '1x',
            'image_mapping_type' => $multipleSizes ? 'sizes' : 'image_style',
            'image_mapping' => $image_mapping
            ];
      } else {
        $dependencies['module'] = [0 => 'custom_bulkimagestyles'];
      }

      $responsive = [
        'uuid' => NULL,
        'langcode' => 'de',
        'status' => true,
        'dependencies' => $dependencies,
        'id' => $responsiveStyleMachineName,
        'label' => $responsiveStyleLabel,
        'image_style_mappings' => $image_style_mappings['image_style_mappings'],
        'breakpoint_group' => $breakpoint_group,
        'fallback_image_style' => $fallbackLabel,
        ];

      $configName = 'responsive_image.styles.'.$responsiveStyleMachineName;
      $config_storage->write($configName, $responsive);

      $markup .= '<br><a href="/admin/config/media/responsive-image-style/'.$responsiveStyleMachineName.'" target="_blank">Responsive Image Style: '.$responsiveStyleLabel.'</a><br>';

      $markup .= '<br><a href="/admin/structure/media/manage/image/display" target="_blank">Media Image Anzeige verwalten</a><br>';
      $markup .= '<a href="/admin/config/media/image-styles" target="_blank">Bildstile verwalten</a>';
    }

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $markup.'</p>',
    ];

    return $build;
  }
}

/**
 * Ermittelt den groessten gemeinsamen Teiler zweier Zahlen.
 * @param int $pNumber1 Die erste ganze Zahl, von der der groessten gemeinsamer Teiler bestimmt werden soll.
 * @param int $pNumber2 Die zweite ganze Zahl, von der der groessten gemeinsamer Teiler bestimmt werden soll.
 * @return int Der groessten gemeinsame Teiler.
 */
function gcd($pNumber1, $pNumber2)
{
    $lNumber1 = abs($pNumber1);
    $lNumber2 = abs($pNumber2);
    $lShiftCount = 0;

    /* Liefere 0 zurÃ¼ck, wenn eine der Zahlen 0 ist. */
    if (!($lNumber1 && $lNumber2)) {
        return 0;
    }

    /* Liefere die erste Zahl zurÃ¼ck, wenn beide Zahlen gleich sind. */
    if ($lNumber1 == $lNumber2) {
        return $lNumber1;
    }

    /* Teile die Zahlen solange durch 2, wie sie den gemeinsamen Primfaktor 2 enthalten. */
    while (!($lNumber1 & 1 || $lNumber2 & 1)) {
        $lNumber1 = $lNumber1 >> 1;
        $lNumber2 = $lNumber2 >> 1;
        $lShiftCount++;
    }

    /* Wende den euklidischen Algorithmus an. */
    if ($lNumber1 & 1) {
        $lDistance = -$lNumber2;
    } else {
        $lDistance = $lNumber1;
    }

    while ($lDistance) {
        while (!($lDistance & 1)) {
            $lDistance = $lDistance >> 1;
        }

        if ($lDistance > 0) {
            $lNumber1 = $lDistance;
        } else {
            $lNumber2 = -$lDistance;
        }

        $lDistance = $lNumber1 - $lNumber2;
    }

    /* Multiplizierte $lShiftCount-mal mit 2 und liefere das Ergebnis zurÃ¼ck. */
    return $lNumber1 << $lShiftCount;
}
