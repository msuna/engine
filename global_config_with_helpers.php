if (!defined('ABSPATH')) { exit; }

/**
 * TISCHLEREI.WIEN – Global Config (single source of truth)
 * Use via: tw_cfg('contact.phone_e164'), tw_cfg('links.offer_anchor'), etc.
 */

function tw_get_config(): array {

  // Full 23 Bezirke (ZIP -> Name)
  $districts = array(
    array('zip'=>'1010','name'=>'Innere Stadt','slug'=>'innere-stadt'),
    array('zip'=>'1020','name'=>'Leopoldstadt','slug'=>'leopoldstadt'),
    array('zip'=>'1030','name'=>'Landstraße','slug'=>'landstrasse'),
    array('zip'=>'1040','name'=>'Wieden','slug'=>'wieden'),
    array('zip'=>'1050','name'=>'Margareten','slug'=>'margareten'),
    array('zip'=>'1060','name'=>'Mariahilf','slug'=>'mariahilf'),
    array('zip'=>'1070','name'=>'Neubau','slug'=>'neubau'),
    array('zip'=>'1080','name'=>'Josefstadt','slug'=>'josefstadt'),
    array('zip'=>'1090','name'=>'Alsergrund','slug'=>'alsergrund'),
    array('zip'=>'1100','name'=>'Favoriten','slug'=>'favoriten'),
    array('zip'=>'1110','name'=>'Simmering','slug'=>'simmering'),
    array('zip'=>'1120','name'=>'Meidling','slug'=>'meidling'),
    array('zip'=>'1130','name'=>'Hietzing','slug'=>'hietzing'),
    array('zip'=>'1140','name'=>'Penzing','slug'=>'penzing'),
    array('zip'=>'1150','name'=>'Rudolfsheim-Fünfhaus','slug'=>'rudolfsheim-fuenfhaus'),
    array('zip'=>'1160','name'=>'Ottakring','slug'=>'ottakring'),
    array('zip'=>'1170','name'=>'Hernals','slug'=>'hernals'),
    array('zip'=>'1180','name'=>'Währing','slug'=>'waehring'),
    array('zip'=>'1190','name'=>'Döbling','slug'=>'doebling'),
    array('zip'=>'1200','name'=>'Brigittenau','slug'=>'brigittenau'),
    array('zip'=>'1210','name'=>'Floridsdorf','slug'=>'floridsdorf'),
    array('zip'=>'1220','name'=>'Donaustadt','slug'=>'donaustadt'),
    array('zip'=>'1230','name'=>'Liesing','slug'=>'liesing'),
  );

  return array(
    'brand' => array(
      'name' => 'Tischlerei Wien',
      'site_url' => home_url('/'),
    ),

    // Contact + Messaging (one place)
    'contact' => array(
      // IMPORTANT: set these to match your header exactly
      'phone_display' => '+43 699 100 00 141',        // e.g. "+43 1 234 56 78"
      'phone_e164'    => '+4369910000141',       // e.g. "+4312345678" or "+43660..."
      'whatsapp_e164' => '+4369910000141',       // e.g. "+43660..."
      'email'         => 'office@tischlerei.wien',
    ),

    // Links / Anchors used by snippets
    'links' => array(
      'offer_anchor'   => '#konfigurator',     // Money-page CTA jump
      'contact_page'   => home_url('/kontakt/'),
      'offers_page'    => home_url('/angebot/'),
      'whatsapp_url'   => '', // optional override, else generated from whatsapp_e164
    ),

    // Placeholders & media defaults
    'placeholders' => array(
      'gallery' => 'https://www.tischlerei.wien/wp-content/uploads/2026/02/tischlerei_platzhalter.png',
    ),

    // Rating meta (if you want centralized)
    'rating' => array(
      'score' => '4,9',
      'count' => '45',
      'label' => 'Google-Bewertungen',
    ),

    // GEO
    'geo' => array(
      'area_served' => 'Wien & Umgebung',
      'districts'   => $districts,
      // default list if a page doesn’t define target_districts
      'default_target_zips' => array('1010','1020','1030','1040','1050','1060','1070','1080','1090','1100','1110','1120','1130','1140','1150','1160','1170','1180','1190','1200','1210','1220','1230'),
    ),
    // i18n (Strings + optional lang override)
    'i18n' => array(
      // optional: override, wenn du mal fix eine Sprache erzwingen willst
      // wenn leer, nimmt tw_lang_current() WP locale
      'lang' => '',

      // string dictionary
      'de' => array(
        'insurance.kicker'   => '💡 Super Tipp zum Kosten sparen',
        'insurance.headline' => 'Einige Schäden können über Versicherungen abgewickelt werden',
        'insurance.text'     => 'Je nach Situation lassen sich Schäden oft über Versicherungen abwickeln – privat über Haushaltsversicherung, bei Hausverwaltungen über Gebäudeversicherung und im Gewerbe über Betriebs-/Haftpflicht.',
        'insurance.b1'       => 'Privat: Haushaltsversicherung (Deckung je nach Polizze)',
        'insurance.b2'       => 'Hausverwaltung: Gebäudeversicherung für Gebäudeteile/Allgemeinflächen',
        'insurance.b3'       => 'Gewerbe: Betriebs-/Haftpflicht – bei Schäden an Dritten oft Haftpflicht',
        'insurance.cta'      => 'Kurz prüfen lassen',
        'insurance.disc'     => 'Hinweis: Deckung/Selbstbehalt hängt vom Vertrag ab. Wir sagen Ihnen, welche Infos/Fotos für die Einschätzung nötig sind.'
      ),

      'en' => array(
        'insurance.kicker'   => '💡 Money-saving tip',
        'insurance.headline' => 'Some damages can be handled via insurance',
        'insurance.text'     => 'Depending on the situation, damages can often be processed via household, building or business/liability insurance.',
        'insurance.b1'       => 'Private: household insurance (coverage depends on policy)',
        'insurance.b2'       => 'Property management: building insurance for building/common areas',
        'insurance.b3'       => 'Commercial: business/liability insurance (often liability for third-party damage)',
        'insurance.cta'      => 'Quick check',
        'insurance.disc'     => 'Note: coverage/deductibles depend on your contract. We’ll tell you what we need for a quick first assessment.'
      ),
    ),
  );
}

/** Get config value by dotted path, e.g. tw_cfg('contact.phone_e164') */
function tw_cfg(string $path, $default = '') {
  $cfg = tw_get_config();
  $keys = explode('.', $path);
  $cur = $cfg;
  foreach ($keys as $k) {
    if (!is_array($cur) || !array_key_exists($k, $cur)) return $default;
    $cur = $cur[$k];
  }
  return $cur;
}

/** E164 helpers */
function tw_normalize_e164(string $e164): string {
  $e164 = trim($e164);
  $e164 = str_replace(array(' ', '-', '(', ')'), '', $e164);
  return $e164;
}
function tw_phone_href(): string {
  $p = tw_normalize_e164((string)tw_cfg('contact.phone_e164',''));
  if ($p === '') return '';
  return 'tel:' . $p;
}
function tw_whatsapp_href(): string {
  $override = (string)tw_cfg('links.whatsapp_url','');
  if ($override !== '') return $override;

  $w = tw_normalize_e164((string)tw_cfg('contact.whatsapp_e164',''));
  if ($w === '') return '';
  // wa.me needs number without "+"
  $w = ltrim($w, '+');
  return 'https://wa.me/' . $w;
}
/** i18n string helper: tw_i18n('insurance.headline') */
function tw_i18n(string $key, string $fallback = ''): string {
  $lang = function_exists('tw_lang_current') ? tw_lang_current() : 'de';

  $dict = tw_cfg('i18n.' . $lang, array());
  if (is_array($dict) && isset($dict[$key])) return (string)$dict[$key];

  // fallback de
  $dict_de = tw_cfg('i18n.de', array());
  if (is_array($dict_de) && isset($dict_de[$key])) return (string)$dict_de[$key];

  return $fallback;
}
/** GEO helpers */
function tw_geo_map(): array {
  $out = array();
  $d = tw_cfg('geo.districts', array());
  if (!is_array($d)) return $out;
  foreach ($d as $row) {
    if (!is_array($row)) continue;
    $zip = (string)($row['zip'] ?? '');
    if ($zip === '') continue;
    $out[$zip] = $row;
  }
  return $out;
}

/** Render district list */
function tw_geo_render(array $zips, string $mode = 'zip_name'): string {
  $map = tw_geo_map();
  $parts = array();

  foreach ($zips as $z) {
    $z = (string)$z;
    if (!isset($map[$z])) continue;
    $name = (string)($map[$z]['name'] ?? '');
    if ($mode === 'zip') $parts[] = $z;
    elseif ($mode === 'name') $parts[] = $name;
    else $parts[] = $z . ' ' . $name;
  }

  return implode(', ', $parts);
}