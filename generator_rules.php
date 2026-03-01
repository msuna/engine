if (!defined('ABSPATH')) { exit; }

/**
 * TW Enterprise Rules
 * - zentrale Defaults + Sprachvarianten
 * - PageType Rules (money/infohub)
 * - optional: per Service-ID / per Cluster
 *
 * Zugriff in Engine:
 * - tw_rules_get('infohub.defaults.fazit_cta')
 * - tw_rules_apply($data, 'infohub', $locale)
 */

if (!function_exists('tw_rules_registry')) {
  function tw_rules_registry(): array {

    // ---- Locale Mapping (erweiterbar) ----
    // WordPress determine_locale() liefert z.B. "de_AT", "de_DE", "en_US"
    // Wir normalisieren auf de/en/tr/ar/sr etc.
    $locales = array(
      'de' => array('de_AT','de_DE','de_CH','de','de_DE_formal'),
      'en' => array('en_US','en_GB','en'),
      'tr' => array('tr_TR','tr'),
      'ar' => array('ar','ar_SA','ar_AE','ar_EG'),
      'sr' => array('sr','sr_RS','sr_Latn','sr_RS@latin'),
    );

    $rules = array(

      // =========================================================
      // GLOBAL
      // =========================================================
      'global' => array(
        'contact' => array(
          'phone_e164' => '+4369910000141',
          'whatsapp'   => 'https://wa.me/4369910000141',
        ),
        'branding' => array(
          'name' => 'Tischlerei Wien',
          'site_url' => 'https://www.tischlerei.wien/',
        ),
        'locales' => $locales,
      ),

      // =========================================================
      // MONEY PAGE RULES
      // =========================================================
      'money' => array(
        'defaults' => array(
          // Optional: falls nicht im JSON, kann Engine das nutzen
          'anchors' => array(
            'angebot' => true,
            'whatsapp' => true,
          ),
          // Optional: Geo-defaults (wenn du willst)
          'geo' => array(
            'area' => 'Wien',
            'districts_short' => array('1010','1030','1060','1070','1090','1100','1120','1150','1180','1210','1220','1230'),
          ),
        ),
      ),

      // =========================================================
      // INFOHUB RULES
      // =========================================================
      'infohub' => array(
        'defaults' => array(
          // Default Fazit CTA (wird automatisch gerendert wenn nicht vorhanden)
          'fazit_cta' => array(
            'title' => 'Fazit: In 10 Minuten zur sinnvollen Lösung',
            'text'  => 'Schicken Sie uns 2–3 Fotos + grobe Maße – wir geben eine schnelle Ersteinschätzung und sagen, was als nächstes sinnvoll ist.',
            'buttons' => array(
              'primary' => array(
                'label' => 'ANGEBOT ANFRAGEN',
                'href'  => '/kueche-kaufen-nach-mass/#angebot',
              ),
              'secondary' => array(
                'label' => 'WHATSAPP SCHNELL-CHECK',
                'href'  => 'https://wa.me/4369910000141',
              ),
            ),
          ),

          // Default Labels (TOC/Sections etc. – optional)
          'labels' => array(
            'toc_title' => 'Inhalt',
            'related_title' => 'Passende Inhalte (InfoHub)',
            'faq_title' => 'FAQ',
          ),
        ),

        // pro Cluster/Service optional überschreiben
        // key = content id (aus JSON "id")
        'by_id' => array(
          // Beispiel:
          // 'kueche-kleine-raeume-optimieren' => array(
          //   'fazit_cta' => array(
          //     'title' => 'Fazit: Kleine Küche – großer Effekt',
          //   )
          // ),
        ),
      ),

      // =========================================================
      // LANGUAGE OVERRIDES (nur Text/Labels)
      // =========================================================
      'i18n' => array(
        'de' => array(
          'infohub' => array(
            'fazit_cta' => array(
              'title' => 'Fazit: In 10 Minuten zur sinnvollen Lösung',
              'text'  => 'Schicken Sie uns 2–3 Fotos + grobe Maße – wir geben eine schnelle Ersteinschätzung.',
              'buttons' => array(
                'primary' => array('label' => 'ANGEBOT ANFRAGEN'),
                'secondary' => array('label' => 'WHATSAPP SCHNELL-CHECK'),
              ),
            ),
          ),
        ),
        'en' => array(
          'infohub' => array(
            'fazit_cta' => array(
              'title' => 'Conclusion: A solid plan in 10 minutes',
              'text'  => 'Send 2–3 photos + rough measurements and we’ll give you a quick first assessment.',
              'buttons' => array(
                'primary' => array('label' => 'REQUEST A QUOTE'),
                'secondary' => array('label' => 'WHATSAPP QUICK CHECK'),
              ),
            ),
          ),
        ),
        'tr' => array(
          'infohub' => array(
            'fazit_cta' => array(
              'title' => 'Sonuç: 10 dakikada doğru çözüm',
              'text'  => '2–3 fotoğraf + yaklaşık ölçüler gönderin; hızlı bir ilk değerlendirme yapalım.',
              'buttons' => array(
                'primary' => array('label' => 'TEKLİF İSTE'),
                'secondary' => array('label' => 'WHATSAPP HIZLI KONTROL'),
              ),
            ),
          ),
        ),
        'ar' => array(
          'infohub' => array(
            'fazit_cta' => array(
              'title' => 'الخلاصة: خطة واضحة خلال 10 دقائق',
              'text'  => 'أرسل ٢–٣ صور + قياسات تقريبية وسنقدم لك تقييمًا أوليًا سريعًا.',
              'buttons' => array(
                'primary' => array('label' => 'اطلب عرض سعر'),
                'secondary' => array('label' => 'واتساب فحص سريع'),
              ),
            ),
          ),
        ),
        'sr' => array(
          'infohub' => array(
            'fazit_cta' => array(
              'title' => 'Zaključak: rešenje za 10 minuta',
              'text'  => 'Pošaljite 2–3 fotografije + okvirne mere – dajemo brzu prvu procenu.',
              'buttons' => array(
                'primary' => array('label' => 'TRAŽI PONUDU'),
                'secondary' => array('label' => 'WHATSAPP BRZI CHECK'),
              ),
            ),
          ),
        ),
      ),
    );

    return $rules;
  }
}

/** -------- Locale normalize -------- */
if (!function_exists('tw_rules_locale')) {
  function tw_rules_locale(): string {
    $loc = function_exists('determine_locale') ? (string) determine_locale() : (string) get_locale();
    $loc = $loc ?: 'de_AT';

    $reg = tw_rules_registry();
    $map = $reg['global']['locales'] ?? array();
    foreach ($map as $short => $variants) {
      foreach ((array)$variants as $v) {
        if ($v === $loc) return (string)$short;
      }
    }
    // fallback: "de_AT" -> "de"
    $dash = strpos($loc, '_');
    if ($dash !== false) return strtolower(substr($loc, 0, $dash));
    return strtolower($loc);
  }
}

/** -------- Safe array get by dot path -------- */
if (!function_exists('tw_rules_get')) {
  function tw_rules_get(string $path, $default = null) {
    $r = tw_rules_registry();
    if ($path === '') return $default;

    $parts = explode('.', $path);
    $cur = $r;
    foreach ($parts as $p) {
      if (!is_array($cur) || !array_key_exists($p, $cur)) return $default;
      $cur = $cur[$p];
    }
    return $cur;
  }
}

/** -------- Deep merge helper (array_replace_recursive wrapper) -------- */
if (!function_exists('tw_rules_merge')) {
  function tw_rules_merge(array $base, array $over): array {
    return array_replace_recursive($base, $over);
  }
}

/**
 * Apply rules to page JSON array
 * - $type: 'money' or 'infohub'
 * - merges:
 *   1) type.defaults
 *   2) type.by_id[ID] (optional)
 *   3) i18n[locale][type] (optional)
 */
if (!function_exists('tw_rules_apply')) {
  function tw_rules_apply(array $data, string $type, ?string $locale = null): array {
    $type = sanitize_key($type);
    $locale = $locale ?: tw_rules_locale();

    $id = isset($data['id']) ? sanitize_key((string)$data['id']) : '';

    $defaults = (array) tw_rules_get($type . '.defaults', array());
    $by_id_all = (array) tw_rules_get($type . '.by_id', array());
    $by_id = ($id !== '' && isset($by_id_all[$id]) && is_array($by_id_all[$id])) ? $by_id_all[$id] : array();

    $i18n_all = (array) tw_rules_get('i18n.' . $locale, array());
    $i18n_type = (isset($i18n_all[$type]) && is_array($i18n_all[$type])) ? $i18n_all[$type] : array();

    // Merge order: defaults -> by_id -> i18n
    $out = tw_rules_merge($defaults, $by_id);
    $out = tw_rules_merge($out, $i18n_type);

    // Apply into data only if key not set / or as nested override:
    // We merge INTO $data so existing JSON can still override rules if you want.
    return tw_rules_merge($out, $data);
  }
}