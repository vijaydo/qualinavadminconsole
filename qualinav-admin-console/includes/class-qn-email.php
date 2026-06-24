<?php

if (!defined('ABSPATH')) {
    exit;
}

class QN_Email
{
    public static function send($to, $subject, $content_html, $args = array())
    {
        $message = self::render($content_html, $args);
        return wp_mail($to, $subject, $message, self::headers(isset($args['headers']) ? $args['headers'] : array()));
    }

    public static function headers($extra_headers = array())
    {
        $site_name = get_bloginfo('name') ? wp_strip_all_tags(get_bloginfo('name')) : 'QualiNav';
        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        $host = $host ? preg_replace('/^www\./i', '', $host) : '';
        $from_email = $host && strpos($host, '.') !== false ? 'noreply@' . $host : get_option('admin_email');
        $from_email = sanitize_email($from_email);
        if (!$from_email || !is_email($from_email)) {
            $from_email = 'wordpress@' . wp_parse_url(network_home_url(), PHP_URL_HOST);
        }

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . $from_email . '>',
        );

        return array_merge($headers, (array) $extra_headers);
    }

    public static function render($content_html, $args = array())
    {
        $brand = class_exists('QN_Branding') ? QN_Branding::get_default_brand() : array();
        $site_name = get_bloginfo('name') ? get_bloginfo('name') : 'QualiNav';
        $bg = self::brand_color($brand, 'background_color', '#F7FAFC');
        $surface = self::brand_color($brand, 'card_color', '#FFFFFF');
        $text = self::brand_color($brand, 'text_color', '#102A43');
        $sidebar = self::brand_color($brand, 'sidebar_color', '#072B49');
        $muted = isset($args['muted_color']) ? sanitize_hex_color($args['muted_color']) : '';
        $muted = $muted ?: '#64748B';
        $preheader = isset($args['preheader']) ? wp_strip_all_tags($args['preheader']) : '';
        $logo_html = self::logo_html($brand, $site_name);

        return '
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light only">
  <style>
    @media (prefers-color-scheme: dark) {
      body, table, td { background-color: ' . esc_html($bg) . ' !important; color: ' . esc_html($text) . ' !important; }
      img { filter: none !important; mix-blend-mode: normal !important; }
    }
    [data-ogsc] img { filter: none !important; mix-blend-mode: normal !important; }
  </style>
</head>
<body style="margin:0; padding:0; background-color:' . esc_attr($bg) . '; font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:' . esc_attr($text) . ';" bgcolor="' . esc_attr($bg) . '">
  ' . ($preheader ? '<div style="display:none; max-height:0; overflow:hidden; opacity:0;">' . esc_html($preheader) . '</div>' : '') . '
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; background-color:' . esc_attr($bg) . '; padding:48px 16px;" bgcolor="' . esc_attr($bg) . '">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:620px; width:100%; background:' . esc_attr($surface) . '; border:1px solid #E2E8F0; border-radius:18px; overflow:hidden; box-shadow:0 18px 48px rgba(15,35,55,0.12);">
          <tr>
            <td style="padding:52px 34px; text-align:center; border-bottom:1px solid #EAF0F6; background:' . esc_attr($sidebar) . ';" bgcolor="' . esc_attr($sidebar) . '">
              ' . $logo_html . '
            </td>
          </tr>
          <tr>
            <td style="padding:34px 38px 30px;">
              ' . $content_html . '
            </td>
          </tr>
          <tr>
            <td style="padding:20px 30px; text-align:center; border-top:1px solid #EAF0F6; background:' . esc_attr($surface) . ';">
              <p style="font-size:13px; line-height:1.6; color:' . esc_attr($muted) . '; margin:0;">Powered by <strong>Grapevine</strong>.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
    }

    public static function button($url, $label, $args = array())
    {
        $brand = class_exists('QN_Branding') ? QN_Branding::get_default_brand() : array();
        $primary = self::brand_color($brand, 'primary_color', '#003B5C');
        $button_text = self::contrast_text($primary);
        $radius = isset($args['radius']) ? preg_replace('/[^0-9a-z.% -]/i', '', (string) $args['radius']) : '12px';

        return '<a href="' . esc_url($url) . '" style="display:inline-block; padding:14px 24px; border-radius:' . esc_attr($radius) . '; background:' . esc_attr($primary) . '; color:' . esc_attr($button_text) . '; font-size:15px; font-weight:800; line-height:1; text-decoration:none; box-shadow:0 8px 18px rgba(0,59,92,0.22);">' . esc_html($label) . '</a>';
    }

    public static function brand_color($brand, $key, $fallback)
    {
        $color = isset($brand[$key]) ? sanitize_hex_color($brand[$key]) : '';
        return $color ?: $fallback;
    }

    public static function contrast_text($background)
    {
        $hex = ltrim((string) $background, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return '#FFFFFF';
        }

        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));
        $brightness = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

        return $brightness > 150 ? '#111827' : '#FFFFFF';
    }

    private static function logo_html($brand, $site_name)
    {
        $logo_url = !empty($brand['logo_url']) ? esc_url($brand['logo_url']) : '';
        if ($logo_url && !self::is_local_url($logo_url)) {
            return '<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:0 auto;"><tr><td align="center" style="text-align:center;"><img src="' . $logo_url . '" alt="' . esc_attr($site_name) . '" width="220" style="border:0; outline:none; text-decoration:none; -ms-interpolation-mode:bicubic; display:block; width:220px; max-width:220px; height:auto; margin:0 auto;" /></td></tr></table>';
        }

        return '<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:0 auto;"><tr><td align="center" style="text-align:center;"><div style="display:inline-block; color:#FFFFFF; font-size:34px; line-height:1.2; font-weight:850; letter-spacing:0; text-align:center;">' . esc_html($site_name) . '</div></td></tr></table>';
    }

    private static function is_local_url($url)
    {
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return true;
        }

        return in_array(strtolower($host), array('localhost', '127.0.0.1', '::1'), true)
            || preg_match('/\.(local|test|invalid)$/i', $host);
    }
}
