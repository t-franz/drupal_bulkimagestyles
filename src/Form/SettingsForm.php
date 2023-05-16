<?php

namespace Drupal\custom_bulkimagestyles\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\breakpoint\BreakpointManagerInterface;
use Drupal\crop\Entity\Crop;
use Drupal\crop\Entity\CropType;

/**
 * Provides a Bulk Image Styles form.
 */
class SettingsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'custom_bulkimagestyles_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $moduleHandler = \Drupal::service('module_handler');


    $form['scale'] = [
      '#type' => 'select',
      '#title' => 'Scale',
      '#options' => [
        'image_scale_and_crop' => 'image_scale_and_crop',
        'image_scale' => 'image_scale',
      ],
    ];
    // https://www.lullabot.com/articles/form-api-states
    // https://www.drupal.org/docs/drupal-apis/form-api/conditional-form-fields
    $ratiostates = [
      'invisible' => [
        ':input[name="scale"]' => ['value' => 'image_scale'],
      ]
    ];
    if ($moduleHandler->moduleExists('image_widget_crop')) {
      $form['crop_type'] = [
        '#title' => t('Crop Type'),
        '#type' => 'select',
        '#options' => \Drupal::service('image_widget_crop.manager')->getAvailableCropType(CropType::getCropTypeNames()),
        '#empty_option' => '--',
        '#description' => 'The type of crop to apply to your image. The machine_name must be of type "x_y". If your Crop Type does not appear here, set an ratio below. (The Crop-Type must already be in use in an image-style to be registered.)',
        '#states' => [
          'visible' => [
            ':input[name="scale"]' => ['value' => 'image_scale_and_crop'],
          ],
        ]
      ];
      $ratiostates = [
        'invisible' => [
          [':input[name="scale"]' => ['value' => 'image_scale'],],
          [':input[name="crop_type"]' => ['!value' => ''],],
        ],
      ];
    }

    $form['ratio'] = [
      '#type' => 'textfield',
      '#title' => 'Seitenverhältnis',
      '#size' => 9,
      '#maxlength' => 9,
      '#default_value' => '16:9',
      '#description' => 'Länge:Breite, z.B. 16:9',
      '#states' => $ratiostates,
    ];

    $form['widths'] = array(
      '#type' => 'textfield',
      '#title' => 'Breiten',
      '#default_value' => '320, 375, 414, 768, 1024, 1220, 1440, 1680, 1920, 2560',
      '#size' => 60,
      '#maxlength' => 128,
      '#description' => 'Bootstrap: 576, 768, 992, 1200, 1400<br>Content: 280, 320, 344, 371, 397, 595, 660<br>Half width: 130, 158, 167, 339, 525<br>Third width: 200, 340, 350, 380, 440, 530, 579',
    );


    if ($moduleHandler->moduleExists('image_style_quality')) {
      $form['highquality'] = array(
        '#type' => 'number',
        '#title' => t('Image quality'),
        '#min' => 0,
        '#max' => 100,
        '#size' => 2,
        '#description' => 'Leave empty for <a href="/admin/config/media/image-toolkit" target="_blank">Standard Quality</a>.'
      );
      $form['highres'] = array(
        '#type' => 'checkbox',
        '#title' => t('Add High-Resolution-Images'),
        '#description' => t('Add second image derivate with doubled image size.'),
      );
      $form['lowquality'] = array(
        '#type' => 'number',
        '#title' => t('Low image quality'),
        '#min' => 0,
        '#max' => 100,
        '#size' => 2,
        '#default_value' => 30,
        '#description' => 'Lower Quality (LQ) for High-Resolution-Images',
        '#states' => [
          'visible' => [
            ':input[name="highres"]' => ['checked' => TRUE],
          ],]
      );
    }

    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => 'Set prefix / name of Image Style',
      '#size' => 60,
      '#maxlength' => 128,
      '#description' => 'Set a prefix to the style name ("MyPrefix 525w"). Leave empty to name the image style depending on image width ("525w") or aspect ratio with image width ("16:9 525w"). If there is a setting for aspect ratio, this setting replaces the text for aspect ratio with custom text ("16:9 525w" -> "MyPrefix 525w").',
    );


    $form['group_responsive'] = array(
      '#type' => 'fieldset',
      '#title' => t('Setting for Responsive Images'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    );

    $form['group_responsive']['generate_responsive_style'] = array(
      '#type' => 'textfield',
      '#title' => 'Generate Responsive Image Style with Sizes Attribute',
      '#size' => 60,
      '#maxlength' => 128,
      '#description' => 'Machine name of (new) responsive style to generate, e.g. banner_new, content_double',
      '#states' => [
        'visible' => [
          ':input[name="responsive_style"]' => ['value' => ''],
        ],
      ]
    );

    // Get list of machine names of responsive images out of config-names responsive_image.styles.*
    $configNames = \Drupal::service('config.storage')->listAll('responsive_image');
    $responsiveStyles = [];
    foreach ($configNames as $key => $value) {
      $style = substr_replace($value, '', 0, strlen('responsive_image.styles.'));
      $responsiveStyles[$style] = $style;
    }
    $form['group_responsive']['responsive_style'] = array(
      '#type' => 'select',
      '#title' => 'Update Responsive Image Style',
      '#options' => $responsiveStyles,
      '#empty_option' => '--',
      '#description' => 'Update selected Responsive Image-Style.',
    );


    $getGroups = \Drupal::service('breakpoint.manager')->getGroups();
    // $activeTheme = \Drupal::service('theme.manager')->getActiveTheme()->getName();
    // $defaultGroup = array_key_exists($activeTheme,$getGroups) ? $activeTheme : 'custom_bulkimagestyles';
    $form['group_responsive']['breakpoint_group'] = array(
      '#type' => 'select',
      '#title' => 'Select Breakpoint Group',
      '#options' => $getGroups,
      '#empty_option' => '--',
      '#default_value' => 'custom_bulkimagestyles',
      '#states' => [
        'visible' => [
            [':input[name="responsive_style"]' => ['!value' => ''],],
            [':input[name="generate_responsive_style"]' => ['!value' => ''],],
          ]
      ]
    );
    $form['group_responsive']['query'] = array(
      '#type' => 'textfield',
      '#title' => 'Media Query',
      '#default_value' => '(min-width:1100px) 1100px, 100vw',
      '#size' => 90,
      '#maxlength' => 256,
      '#states' => [
        'visible' => [
            [':input[name="responsive_style"]' => ['!value' => ''],],
            [':input[name="generate_responsive_style"]' => ['!value' => ''],],
          ]
      ]
    );

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Generiere Image-Styles',
      '#weight' => 90,
    ];

    $form['markup'] = [
      '#markup' => _help(),
      '#weight' => 100,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
/*     if (mb_strlen($form_state->getValue('message')) < 10) {
      $form_state->setErrorByName('name', $this->t('Message should be at least 10 characters.'));
    } */
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    //$this->messenger()->addStatus($this->t('Generate ...'));

    $generate_responsive_style = $form_state->getValue('generate_responsive_style');
    $select_responsive_style = $form_state->getValue('responsive_style');

    $responsive_style = ($select_responsive_style) ? $select_responsive_style : $generate_responsive_style;

    $scale = $form_state->getValue('scale');
    if ($scale != 'image_scale') {
      $ratio = ($form_state->getValue('crop_type')) ? str_replace('_', ':',$form_state->getValue('crop_type')) : $form_state->getValue('ratio');
    } else {
      $ratio = null;
    }
    $crop_type = $form_state->getValue('crop_type');
    $name = $form_state->getValue('name');
    $breakpoint_group = $form_state->getValue('breakpoint_group');
    $widths=$form_state->getValue('widths');
    $query=$form_state->getValue('query');
    $highquality=$form_state->getValue('highquality');
    $lowquality= ($form_state->getValue('highres')) ? $form_state->getValue('lowquality') : '';

    $url = \Drupal\Core\Url::fromRoute('custom_bulkimagestyles.generate')
          ->setRouteParameters(
            array(
              'responsive_style'=>$responsive_style,
              'scale'=>$scale,
              'ratio'=>$ratio,
              'crop_type'=>$crop_type,
              'name'=>$name,
              'breakpoint_group'=>$breakpoint_group,
              'widths'=>$widths,
              'query'=>$query,
              'highquality'=>$highquality,
              'lowquality'=>$lowquality,
            )
          );
    $form_state->setRedirectUrl($url);
  }
}

function _help() {
  $markup = <<<END
  <h3>Beispiel ohne Sidebar:</h3>
  mobile-gap: 20px
  desktop-gap: 80px
  max-width: 1100px
  page-padding: 40px


  <b>third (max: 1100px):</b>
  (min-width:1140px) 340px,
  (min-width:768px) calc((100vw - 2/2*desktop-gap - 40px) / 3)
  (min-width:620px) calc((100vw - 2/2*mobile-gap - 40px) / 3)
  calc(100vw - 40px)

  <i>(min-width:1140px) 340px, (min-width:768px) calc((100vw - 120px) / 3), (min-width:620px) calc((100vw - 60px) / 3), calc(100vw - 40px)</i>

  Width: 200, 340, 350, 380, 440, 530, 579



  <b>half (max: 1100px, --minimage:80px):</b>
  First Breakpoint: --minimage:80px*2 + 40px + --mobilegap: 20px)
  Second Breakpoint: switch of --gap

  (min-width:1140px) 525px,
  (min-width:768px) calc((100vw - 1/2*desktop-gap - 40px) / 2)
  (min-width:220px) calc((100vw - 1/2*mobile-gap - 40px) / 2)
  calc(100vw - 40px)

  <i>(min-width:1400px) 660px, (min-width:786px) calc(50vw - 40px), calc(100vw - 40px)</i>
  <i>(min-width:1140px) 525px, (min-width:768px) calc((100vw - 80px) / 2), (min-width:220px) calc((100vw - 50px) / 2), calc(100vw - 40px)</i>

  Width: 130, 158, 167, 339, 525



  <h3>Mit Sidebar: Calculate Media-Query (layout.css->.layout-content):</h3>
  <i>(min-width:1140px) 650px, (min-width:960px) 610px, (min-width:680px) calc( (100vw - (2*20px) ) * .6 - 40px), calc(100vw - 40px)</i>

  .max-with: padding  = 2*20px
  .layout-content width: 60% ("*.6")
  .layout-content padding-right: 40px
  .layout-content max-width: 650px

  (100vw - maxWidthPadding) * layoutContentWidth - LayoutContentPaddingRight
  320-40	= 280
  360-40	= 320
  411-40	= 371
  (680-40)*.6 - 40 = 344
  (768-40)*.6 - 40 = 397
  (960-40)*.69 - 40 = 595
  1140 = 650
  END;
  return nl2br($markup);
}
