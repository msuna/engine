// ===================== TEIL 1/2 =====================

if (!defined('ABSPATH')) { exit; }

/**
 * TISCHLEREI.WIEN – LP/InfoHub Engine (Enterprise, multilingual-ready)
 * JSON im Page Content:
 *   <script type="application/json" id="lp-data-{id}">...</script>
 *   <script type="application/json" id="ih-data-{id}">...</script>
 *
 * Shortcodes:
 * [lp_money_page id="..."]
 * [lp_schema id="..."]
 * [lp_infohub_page id="..."]
 * [lp_infohub_schema id="..."]
 *
 * Neu (Enterprise):
 * - InfoBox System (Versicherung / Tipp / Hinweis) via JSON:
 *   Money:  "info_boxes": [ { placement: "money.after_trust" | "money.before_inquiry" | "money.after_pricing" | "money.after_steps" } ]
 *   InfoHub Section: "info_box": { ... } (inline in Section)
 * - Multilingual: optional "text_i18n": { "de": {...}, "tr": {...}, ... } + language detection
 * - Bullet/Icon Cleanup: keine doppelten "✅/•" Prefixe mehr – Flatsome/CSS übernimmt
 * - Money Hero Head/Card: schöner Kopfbereich (Padding + runde Ecken wie InfoHub)
 */

/** =========================================================
 *  Helpers
 *  ========================================================= */

if (!function_exists('lp_cfg')) {
  function lp_cfg(string $path, $default = '') {
    return function_exists('tw_cfg') ? tw_cfg($path, $default) : $default;
  }
}

/** --- Language detection (Enterprise, safe fallback) --- */
if (!function_exists('tw_lang_current')) {
  function tw_lang_current(): string {
    // 1) zentrale Config (wenn vorhanden)
    $cfg = lp_cfg('i18n.lang', '');
    if (is_string($cfg) && $cfg !== '') return sanitize_key($cfg);

    // 2) WP core locale
    $loc = function_exists('determine_locale') ? determine_locale() : get_locale();
    $loc = is_string($loc) ? $loc : 'de_DE';
    $loc = strtolower($loc);
    // "de_at" -> "de"
    $parts = explode('_', str_replace('-', '_', $loc));
    $lang = isset($parts[0]) ? $parts[0] : 'de';
    $lang = sanitize_key($lang);
    return $lang !== '' ? $lang : 'de';
  }
}

/** --- i18n picker: box fields (text_i18n overrides base) --- */
if (!function_exists('tw_i18n_pick')) {
  function tw_i18n_pick(array $box, string $lang): array {
    if (isset($box['text_i18n']) && is_array($box['text_i18n'])) {
      $ti = $box['text_i18n'];
      // exact match
      if (isset($ti[$lang]) && is_array($ti[$lang])) {
        return array_merge($box, $ti[$lang]);
      }
      // fallback to de
      if (isset($ti['de']) && is_array($ti['de'])) {
        return array_merge($box, $ti['de']);
      }
      // fallback first available
      foreach ($ti as $k => $v) {
        if (is_array($v)) return array_merge($box, $v);
      }
    }
    return $box;
  }
}

/** --- Soft cleanup for bullet-like prefixes (NO regex, WAF-friendly) --- */
if (!function_exists('tw_strip_bullet_prefix')) {
  function tw_strip_bullet_prefix(string $s): string {
    $s = trim($s);
    if ($s === '') return $s;

    // common prefixes that cause duplicates when theme already renders icons
    $prefixes = array('✅', '✔', '✓', '•', '‣', '→', '➜', '-', '–', '—');
    foreach ($prefixes as $p) {
      if (strpos($s, $p) === 0) {
        $s = trim(substr($s, strlen($p)));
        // allow double prefix (e.g. "✅ ✅ text")
        $s = ltrim($s);
      }
    }
    return $s;
  }
}

/** --- Strip leading step numbers like "1.", "01)", "2 -", "3:" (NO regex, WAF-friendly) --- */
if (!function_exists('tw_strip_leading_step_number')) {
  function tw_strip_leading_step_number(string $s): string {
    $s = trim($s);
    if ($s === '') return $s;

    $len = strlen($s);
    $i = 0;

    // skip leading spaces
    while ($i < $len && $s[$i] === ' ') $i++;

    // must start with a digit
    if ($i >= $len || $s[$i] < '0' || $s[$i] > '9') {
      return $s;
    }

    // consume digits
    $start = $i;
    while ($i < $len && $s[$i] >= '0' && $s[$i] <= '9') $i++;

    // optional whitespace
    while ($i < $len && $s[$i] === ' ') $i++;

    // optional separators after number
    if ($i < $len) {
      $ch = $s[$i];
      $seps = array('.', ')', ':', '-', '–', '—');
      if (in_array($ch, $seps, true)) {
        $i++;
        // optional whitespace after separator
        while ($i < $len && $s[$i] === ' ') $i++;
        $out = trim(substr($s, $i));
        return $out !== '' ? $out : trim(substr($s, $start)); // fallback if empty
      }
    }

    // if no known separator, keep original
    return $s;
  }
}

/** =========================================================
 *  JSON readers
 *  ========================================================= */

if (!function_exists('lp_get_content')) {
  function lp_get_content(string $id): array {
    $id = sanitize_key($id);

    $post_id = get_the_ID();
    if (!$post_id) { $post_id = get_queried_object_id(); }
    if (!$post_id) return array();

    $cache_key = 'lp_data_' . $post_id . '_' . $id;
    $cached = get_transient($cache_key);
    if (is_array($cached)) return $cached;

    $content = get_post_field('post_content', $post_id);
    if (!is_string($content) || $content === '') return array();

    $script_id = 'lp-data-' . $id;

    $pattern = '#<script(?=[^>]*\btype=["\']application/json["\'])(?=[^>]*\bid=["\']'
             . preg_quote($script_id, '#')
             . '["\'])[^>]*>(.*?)</script>#is';

    if (!preg_match($pattern, $content, $m)) return array();

    $json = trim((string)$m[1]);
    if ($json === '') return array();

    $data = json_decode($json, true);
    if (!is_array($data)) return array();

    set_transient($cache_key, $data, 10 * MINUTE_IN_SECONDS);
    return $data;
  }
}

if (!function_exists('ih_get_content')) {
  function ih_get_content(string $id): array {
    $id = sanitize_key($id);

    $post_id = get_the_ID();
    if (!$post_id) { $post_id = get_queried_object_id(); }
    if (!$post_id) return array();

    $cache_key = 'ih_data_' . $post_id . '_' . $id;
    $cached = get_transient($cache_key);
    if (is_array($cached)) return $cached;

    $content = get_post_field('post_content', $post_id);
    if (!is_string($content) || $content === '') return array();

    $script_id = 'ih-data-' . $id;

    $pattern = '#<script(?=[^>]*\btype=["\']application/json["\'])(?=[^>]*\bid=["\']'
             . preg_quote($script_id, '#')
             . '["\'])[^>]*>(.*?)</script>#is';

    if (!preg_match($pattern, $content, $m)) return array();

    $json = trim((string)$m[1]);
    if ($json === '') return array();

    $data = json_decode($json, true);
    if (!is_array($data)) return array();

    set_transient($cache_key, $data, 10 * MINUTE_IN_SECONDS);
    return $data;
  }
}

/** =========================================================
 *  Shared render helpers (lists + InfoBoxes)
 *  ========================================================= */

if (!function_exists('tw_render_ul_plain')) {
  function tw_render_ul_plain(array $items, string $class = ''): string {
    $lis = array();
    foreach ($items as $it) {
      if (!is_string($it)) continue;
      $t = tw_strip_bullet_prefix($it);
      if ($t === '') continue;
      $lis[] = '<li>' . esc_html($t) . '</li>';
    }
    if (empty($lis)) return '';
    $cls = $class !== '' ? ' class="' . esc_attr($class) . '"' : '';
    return '<ul' . $cls . '>' . implode('', $lis) . '</ul>';
  }
}

/** --- Enterprise InfoBox renderer (Money + InfoHub) --- */
if (!function_exists('tw_render_infobox')) {
  function tw_render_infobox(array $box, string $context = 'money'): string {
    $lang = tw_lang_current();
    $box = tw_i18n_pick($box, $lang);

    $variant = isset($box['variant']) ? sanitize_key((string)$box['variant']) : 'info';
    $tone    = isset($box['tone']) ? sanitize_key((string)$box['tone']) : 'tip';

    $title    = isset($box['title']) ? (string)$box['title'] : '';
    $headline = isset($box['headline']) ? (string)$box['headline'] : '';
    $text     = isset($box['text_html']) ? (string)$box['text_html'] : '';
    $bullets  = (isset($box['bullets']) && is_array($box['bullets'])) ? $box['bullets'] : array();
    $disc     = isset($box['disclaimer']) ? (string)$box['disclaimer'] : '';

    $cta = (isset($box['cta']) && is_array($box['cta'])) ? $box['cta'] : array();
    $cta_label = isset($cta['label']) ? (string)$cta['label'] : '';
    $cta_href  = isset($cta['href']) ? (string)$cta['href'] : '';

    $cls = 'tw-infobox tw-infobox--' . $variant . ' tw-infobox--tone-' . $tone . ' tw-infobox--ctx-' . sanitize_key($context);

    $html  = '<aside class="' . esc_attr($cls) . '">';
    if ($title !== '') {
      $html .= '<div class="tw-infobox__kicker">' . esc_html($title) . '</div>';
    }
    if ($headline !== '') {
      $html .= '<h3 class="tw-infobox__headline">' . esc_html($headline) . '</h3>';
    }
    if ($text !== '') {
      $html .= '<div class="tw-infobox__text">' . wp_kses_post($text) . '</div>';
    }
    if (!empty($bullets)) {
      // No "✅/•" prefixes to avoid double icons – theme/CSS handles marker.
      $html .= '<div class="tw-infobox__bullets">' . tw_render_ul_plain($bullets, 'tw-infobox__ul') . '</div>';
    }
    if ($cta_label !== '' && $cta_href !== '') {
      $html .= '<div class="tw-infobox__cta">';
      $html .= '<a class="tw-infobox__btn" href="' . esc_url($cta_href) . '">' . esc_html($cta_label) . '</a>';
      $html .= '</div>';
    }
    if ($disc !== '') {
      $html .= '<div class="tw-infobox__disc">' . esc_html($disc) . '</div>';
    }
    $html .= '</aside>';

    return $html;
  }
}

/** --- Money placements: render all boxes for a placement --- */
if (!function_exists('lp_render_info_boxes')) {
  function lp_render_info_boxes(array $c, string $placement): string {
    $boxes = (isset($c['info_boxes']) && is_array($c['info_boxes'])) ? $c['info_boxes'] : array();
    if (empty($boxes)) return '';

    $out = '';
    foreach ($boxes as $b) {
      if (!is_array($b)) continue;
      $pl = isset($b['placement']) ? sanitize_key((string)$b['placement']) : '';
      if ($pl !== sanitize_key($placement)) continue;
      $out .= tw_render_infobox($b, 'money');
    }
    return $out;
  }
}

/** =========================================================
 *  MONEY RENDERERS
 *  ========================================================= */

// (ab hier bleibt dein Code wie im Original; Teil 2 folgt)
// ===================== TEIL 2/2 =====================

if (!function_exists('lp_render_hero')) {
  function lp_render_hero(array $c): string {
    $hero = $c['hero'] ?? array();
    if (!is_array($hero) || empty($hero)) return '';

    $h1 = (string)($hero['h1'] ?? '');
    $lead_html = (string)($hero['lead_html'] ?? '');

    $cta = (isset($hero['cta']) && is_array($hero['cta'])) ? $hero['cta'] : array();
    $cta_sc = '';
    if (!empty($cta)) {
      $cta_sc = sprintf(
        '[lp_hero_cta link="%s" btn="%s" rating="%s" count="%s" intent="%s"]',
        esc_attr((string)($cta['link'] ?? lp_cfg('links.offer_anchor', '#angebot'))),
        esc_attr((string)($cta['btn'] ?? 'ANGEBOT EINHOLEN')),
        esc_attr((string)($cta['rating'] ?? (lp_cfg('rating.score','4,9') . ' Sterne'))),
        esc_attr((string)($cta['count'] ?? ('von ' . lp_cfg('rating.count','45') . ' ' . lp_cfg('rating.label','Bewertungen')))),
        esc_attr((string)($cta['intent'] ?? 'Antwort in der Regel zeitnah während der Servicezeiten.'))
      );
    }

    $badges = (isset($hero['badges']) && is_array($hero['badges'])) ? $hero['badges'] : array();

    ob_start(); ?>
    <div class="lp-hero">
      <?php if ($h1 !== ''): ?>
        <h1 class="lp-hero__title"><?php echo esc_html($h1); ?></h1>
      <?php endif; ?>

      <?php if ($lead_html !== ''): ?>
        <p class="lp-hero__intro"><?php echo wp_kses_post($lead_html); ?></p>
      <?php endif; ?>

      <?php if ($cta_sc !== ''): ?>
        <?php echo do_shortcode($cta_sc); ?>
        <?php echo do_shortcode('[gap height="14px"]'); ?>
      <?php endif; ?>

      <?php if (!empty($badges)): ?>
        <div class="lp-hero__badges">
          <?php foreach ($badges as $b): ?>
            <div class="lp-hero__badge">
              <strong><?php echo esc_html((string)($b['title'] ?? '')); ?></strong><br>
              <span><?php echo esc_html((string)($b['text'] ?? '')); ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <?php
    return (string)ob_get_clean();
  }
}

if (!function_exists('lp_render_gallery')) {
  function lp_render_gallery(array $c): string {
    $g = $c['gallery'] ?? array();
    if (!is_array($g) || empty($g)) return '';

    $folder = (string)($g['folder'] ?? '');
    $limit  = (int)($g['limit'] ?? 3);
    $hero   = (int)($g['hero'] ?? 1);

    $out = '';
    if (shortcode_exists('spiegel_gallery')) {
      $out .= do_shortcode(
        '[spiegel_gallery folder="' . esc_attr($folder) . '" limit="' . absint($limit) . '" hero="' . absint($hero) . '"]'
      );
    }
    return $out;
  }
}

if (!function_exists('lp_render_material')) {
  function lp_render_material(array $c): string {
    $m = $c['material'] ?? array();
    if (!is_array($m) || empty($m)) return '';

    $title = (string)($m['title'] ?? '');
    $pars  = (isset($m['paragraphs_html']) && is_array($m['paragraphs_html'])) ? $m['paragraphs_html'] : array();

    ob_start(); ?>
    <section class="lp-material">
      <?php if ($title !== ''): ?>
        <h2 class="lp-section-title"><?php echo esc_html($title); ?></h2>
      <?php endif; ?>
      <?php foreach ($pars as $p): ?>
        <p><?php echo wp_kses_post((string)$p); ?></p>
      <?php endforeach; ?>
    </section>
    <?php
    return (string)ob_get_clean();
  }
}

if (!function_exists('lp_render_steps')) {
  function lp_render_steps(array $c): string {
    $s = $c['steps'] ?? array();
    if (!is_array($s) || empty($s)) return '';

    $title = (string)($s['title'] ?? '');
    $items = (isset($s['items']) && is_array($s['items'])) ? $s['items'] : array();
    $after = (isset($s['after_paragraphs']) && is_array($s['after_paragraphs'])) ? $s['after_paragraphs'] : array();

    ob_start(); ?>
    <section class="lp-steps">
      <?php if ($title !== ''): ?>
        <h2 class="lp-section-title"><?php echo esc_html($title); ?></h2>
      <?php endif; ?>

      <?php if (!empty($items)): ?>
        <div class="lp-steps__grid lp-grid lp-grid--5">
          <?php foreach ($items as $i => $step): ?>
            <div class="step-col">
              <div class="step-card">
                <div class="step-dot"><?php echo esc_html((string)($step['number'] ?? ($i+1))); ?></div>
                <strong><?php echo esc_html((string)($step['title'] ?? '')); ?></strong><br>
                <?php echo esc_html((string)($step['text'] ?? '')); ?>
              </div>
              <?php if ($i < count($items)-1): ?>
                <span class="step-arrow" aria-hidden="true">→</span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php foreach ($after as $p): ?>
        <p><?php echo esc_html((string)$p); ?></p>
      <?php endforeach; ?>
    </section>
    <?php
    return (string)ob_get_clean();
  }
}

if (!function_exists('lp_render_trust')) {
  function lp_render_trust(array $c): string {
    $t = $c['trust'] ?? array();
    if (!is_array($t) || empty($t)) return '';

    $title = (string)($t['title'] ?? '');
    $items = (isset($t['items']) && is_array($t['items'])) ? $t['items'] : array();

    ob_start(); ?>
    <section class="lp-trust-grid">
      <?php if ($title !== ''): ?>
        <h2 class="lp-section-title"><?php echo esc_html($title); ?></h2>
      <?php endif; ?>
      <div class="lp-grid lp-grid--2">
        <?php foreach ($items as $it): ?>
          <div class="trust-card">
            <strong><?php echo esc_html((string)($it['title'] ?? '')); ?></strong>
            <p><?php echo esc_html((string)($it['text'] ?? '')); ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php
    return (string)ob_get_clean();
  }
}

if (!function_exists('lp_render_inquiry')) {
  function lp_render_inquiry(array $c): string {
    $q = isset($c['inquiry']) && is_array($c['inquiry']) ? $c['inquiry'] : array();
    if (empty($q)) return '';

    $title   = isset($q['title']) ? (string)$q['title'] : '';
    $text    = isset($q['text']) ? (string)$q['text'] : '';
    $form_id = isset($q['form_wpcode_id']) ? (int)$q['form_wpcode_id'] : 0;

    $btns = (isset($q['cta_buttons']) && is_array($q['cta_buttons'])) ? $q['cta_buttons'] : array();

    $call_label = isset($btns['call']['label']) ? (string)$btns['call']['label'] : 'JETZT ANRUFEN';
    $call_phone = isset($btns['call']['phone']) ? (string)$btns['call']['phone'] : '';

    $wa_label = isset($btns['whatsapp']['label']) ? (string)$btns['whatsapp']['label'] : 'WHATSAPP';
    $wa_url   = isset($btns['whatsapp']['url']) ? (string)$btns['whatsapp']['url'] : '';

    $tel_href = '';
    if ($call_phone !== '') {
      $p = $call_phone;
      $p = str_replace(array(' ', "\t", "\n", "\r", '-', '(', ')', '.', '/', '\\'), '', $p);
      if ($p !== '' && $p[0] !== '+') $p = '+' . $p;
      $tel_href = 'tel:' . $p;
    }

    $html  = '';
    $html .= '<section class="lp-inquiry tw-inquiry" id="angebot">';
    $html .=   '<div class="tw-inquiry__card">';
    $html .=     '<div class="tw-inquiry__badge">Schnell &amp; unverbindlich</div>';

    if ($title !== '') {
      $html .= '<h2 class="tw-inquiry__title">' . esc_html($title) . '</h2>';
    }
    if ($text !== '') {
      $html .= '<p class="tw-inquiry__text">' . wp_kses_post($text) . '</p>';
    }

    // Enterprise: 1-spaltig (du wolltest das so) – CSS kann grid trotzdem handeln
    $html .=     '<div class="tw-inquiry__grid tw-inquiry__grid--single">';
    $html .=       '<div class="tw-inquiry__actions">';

    if ($tel_href !== '') {
      $html .= '<a class="tw-inquiry__btn tw-inquiry__btn--call" href="' . esc_attr($tel_href) . '">'
            .  esc_html($call_label)
            .  '</a>';
    }

    if ($wa_url !== '') {
      $html .= '<a class="tw-inquiry__btn tw-inquiry__btn--wa" href="' . esc_url($wa_url) . '" target="_blank" rel="noopener">'
            .  esc_html($wa_label)
            .  '</a>';
    }

    $html .=         '<small class="tw-inquiry__note">Tipp: Maße + 2–3 Fotos reichen für eine erste Einschätzung.</small>';
    $html .=       '</div>'; // actions

    $html .=       '<div class="tw-inquiry__form">';
    if ($form_id > 0) {
      $html .= do_shortcode('[wpcode id="' . absint($form_id) . '"]');
    }
    $html .=       '</div>'; // form

    $html .=     '</div>';   // grid
    $html .=   '</div>';     // card
    $html .= '</section>';

    return $html;
  }
}

if (!function_exists('lp_render_faq')) {
  function lp_render_faq(array $c): string {
    $f = $c['faq'] ?? array();
    if (!is_array($f) || empty($f)) return '';

    $title = (string)($f['title'] ?? '');
    $subtitle = (string)($f['subtitle'] ?? '');
    $items = (isset($f['items']) && is_array($f['items'])) ? $f['items'] : array();
    if (empty($items)) return '';

    ob_start(); ?>
    <section class="lp-faq mini-faq-wrap">
      <?php if ($title !== ''): ?>
        <h2 class="lp-section-title"><?php echo esc_html($title); ?></h2>
      <?php endif; ?>
      <?php if ($subtitle !== ''): ?>
        <p class="lp-faq__intro"><?php echo esc_html($subtitle); ?></p>
      <?php endif; ?>

      <div class="lp-faq__accordion-wrap">
        <?php if (shortcode_exists('accordion') && shortcode_exists('accordion-item')): ?>
          <?php
            $acc = "[accordion]";
            foreach ($items as $it) {
              $q = esc_attr((string)($it['question'] ?? ''));
              $a_html = wp_kses_post((string)($it['answer_html'] ?? ''));
              if ($q === '' || $a_html === '') continue;
              $acc .= '[accordion-item title="' . $q . '"]' . $a_html . '[/accordion-item]';
            }
            $acc .= "[/accordion]";
            echo do_shortcode($acc);
          ?>
        <?php else: ?>
          <?php foreach ($items as $it): ?>
            <?php $q = (string)($it['question'] ?? ''); ?>
            <?php $a_html = wp_kses_post((string)($it['answer_html'] ?? '')); ?>
            <?php if ($q === '' || $a_html === '') continue; ?>
            <details>
              <summary><?php echo esc_html($q); ?></summary>
              <div class="lp-faq__answer"><?php echo $a_html; ?></div>
            </details>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
    <?php
    return (string)ob_get_clean();
  }
}

if (!function_exists('lp_render_praxis')) {
  function lp_render_praxis(array $c): string {
    $p = $c['praxis'] ?? array();
    if (!is_array($p) || empty($p)) return '';

    $title = (string)($p['title'] ?? '');
    $items = (isset($p['items']) && is_array($p['items'])) ? $p['items'] : array();

    ob_start(); ?>
    <section class="lp-praxis">
      <?php if ($title !== ''): ?>
        <h2 class="lp-section-title"><?php echo esc_html($title); ?></h2>
      <?php endif; ?>
<?php foreach ($items as $idx => $it): $n = $idx + 1; ?>
  <div class="lp-praxis__item">
    <?php $h = (string)($it['heading'] ?? ''); ?>
    <h3 class="lp-praxis__h" data-step="<?php echo esc_attr((string)$n); ?>"><?php echo esc_html( tw_strip_leading_step_number($h) ); ?></h3>
    <p class="lp-praxis__p"><?php echo esc_html((string)($it['text'] ?? '')); ?></p>
  </div>
<?php endforeach; ?>
</section>
    <?php
    return (string)ob_get_clean();
  }
}

/** =========================================================
 *  MONEY SCHEMA
 *  ========================================================= */

if (!function_exists('lp_render_schema')) {
  function lp_render_schema(array $c): string {
    $s = $c['schema'] ?? array();
    if (!is_array($s) || empty($s)) return '';

    $biz = isset($s['business']) && is_array($s['business']) ? $s['business'] : array();
    $business_name = (string)($biz['name'] ?? lp_cfg('brand.name',''));
    $business_url  = (string)($biz['url'] ?? lp_cfg('brand.site_url',''));
    $business_tel  = (string)($biz['telephone'] ?? lp_cfg('contact.phone_e164',''));

    $faq_items = (isset($c['faq']['items']) && is_array($c['faq']['items'])) ? $c['faq']['items'] : array();
    $faq_graph = array();

    if (!empty($faq_items)) {
      $mainEntity = array();
      foreach ($faq_items as $it) {
        $q = wp_strip_all_tags((string)($it['question'] ?? ''));
        $a = wp_strip_all_tags((string)($it['answer_html'] ?? ''));
        if ($q === '' || $a === '') continue;
        $mainEntity[] = array(
          '@type' => 'Question',
          'name'  => $q,
          'acceptedAnswer' => array('@type' => 'Answer', 'text' => $a),
        );
      }
      if (!empty($mainEntity)) {
        $faq_graph = array('@type' => 'FAQPage', 'mainEntity' => $mainEntity);
      }
    }

    $provider = array(
      '@type' => 'LocalBusiness',
      'name' => $business_name,
      'url'  => $business_url,
      'telephone' => $business_tel,
    );

    $graph = array();

    $graph[] = array(
      '@type' => 'Service',
      'name' => (string)($s['service_name'] ?? ''),
      'serviceType' => (string)($s['serviceType'] ?? ''),
      'areaServed' => (string)($s['areaServed'] ?? lp_cfg('geo.area_served','Wien')),
      'url' => (string)($s['page_url'] ?? ''),
      'provider' => $provider,
    );

    if (!empty($s['service_name']) || !empty($s['product_description'])) {
      $graph[] = array(
        '@type' => 'Product',
        'name' => (string)($s['service_name'] ?? ''),
        'description' => (string)($s['product_description'] ?? ''),
        'brand' => array('@type' => 'Brand', 'name' => $business_name),
        'url' => (string)($s['page_url'] ?? ''),
      );
    }

    if (!empty($faq_graph)) $graph[] = $faq_graph;

    $payload = array('@context' => 'https://schema.org', '@graph' => $graph);
    return '<script type="application/ld+json">' . wp_json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . '</script>';
  }
}

/** =========================================================
 *  MONEY SHORTCODES
 *  ========================================================= */

if (!function_exists('lp_sc_money_page')) {
  function lp_sc_money_page($atts): string {
    $atts = shortcode_atts(array('id' => ''), $atts, 'lp_money_page');
    $id = sanitize_key((string)$atts['id']);
    if ($id === '') return '';

    $c = lp_get_content($id);
    if (empty($c)) {
      if (current_user_can('manage_options')) {
        return '<div style="padding:12px;border:1px solid #f00;">LP: Kein JSON gefunden. Erwartet: <code>&lt;script id="lp-data-' . esc_html($id) . '" type="application/json"&gt;...&lt;/script&gt;</code></div>';
      }
      return '';
    }

    ob_start(); ?>
    <div class="lp-shell lp-kueche lp-money">

      <!-- Money: schöner Kopfbereich (wie InfoHub) -->
      <div class="lp-content-card lp-money__head">
        <div class="lp-shell__hero-wrap">
          <div class="lp-shell__hero-left">
            <?php echo lp_render_hero($c); ?>
          </div>
          <div class="lp-shell__hero-right">
            <?php echo lp_render_gallery($c); ?>
          </div>
        </div>
      </div>

      <div class="lp-content-card lp-money__body">
        <?php echo lp_render_material($c); ?>

        <?php echo lp_render_steps($c); ?>
<?php
  // Insurance Tip Box (modular)
  if (function_exists('tw_render_infobox_insurance')) {
    echo tw_render_infobox_insurance($c, 'money');
  }
?>
        <?php echo lp_render_info_boxes($c, 'money.after_steps'); ?>

        <?php echo lp_render_trust($c); ?>
        <?php echo lp_render_info_boxes($c, 'money.after_trust'); ?>

        <?php echo lp_render_info_boxes($c, 'money.before_inquiry'); ?>
        <?php echo lp_render_inquiry($c); ?>

        <?php echo lp_render_faq($c); ?>
      </div>

      <?php echo lp_render_praxis($c); ?>

      <?php if (!empty($c['anchors']['whatsapp'])): ?>
        <div id="whatsapp"></div>
      <?php endif; ?>
    </div>
    <?php
    return (string)ob_get_clean();
  }
}

if (!function_exists('lp_sc_schema')) {
  function lp_sc_schema($atts): string {
    $atts = shortcode_atts(array('id' => ''), $atts, 'lp_schema');
    $id = sanitize_key((string)$atts['id']);
    if ($id === '') return '';
    $c = lp_get_content($id);
    if (empty($c)) return '';
    return lp_render_schema($c);
  }
}

add_shortcode('lp_money_page', 'lp_sc_money_page');
add_shortcode('lp_schema', 'lp_sc_schema');

// 
// // ===================== INFOHUB-TEIL (komplett) =====================

/** =========================================================
 *  INFOHUB RENDERERS
 *  ========================================================= */

if (!function_exists('ih_render_toc')) {
  function ih_render_toc(array $c): string {
    $toc = isset($c['toc']) && is_array($c['toc']) ? $c['toc'] : array();
    $items = isset($toc['items']) && is_array($toc['items']) ? $toc['items'] : array();
    if (empty($items)) return '';

    $title = (string)($toc['title'] ?? 'Inhalt');

    ob_start(); ?>
    <div class="lp-content-card ih-toc" id="inhalt">
      <h2 class="lp-section-title"><?php echo esc_html($title); ?></h2>
      <ul class="ih-toc__list">
        <?php foreach ($items as $it):
          $label = (string)($it['label'] ?? '');
          $anchor = (string)($it['anchor'] ?? '');
          if ($label === '' || $anchor === '') continue;
        ?>
          <li><a href="#<?php echo esc_attr($anchor); ?>"><?php echo esc_html($label); ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php
    return (string)ob_get_clean();
  }
}

if (!function_exists('ih_render_quick_fazit')) {
  function ih_render_quick_fazit(array $c): string {
    $q = isset($c['quick_fazit']) && is_array($c['quick_fazit']) ? $c['quick_fazit'] : array();
    $cards = isset($q['cards']) && is_array($q['cards']) ? $q['cards'] : array();
    if (empty($cards)) return '';

    $title = (string)($q['title'] ?? 'Kurz-Fazit');

    ob_start(); ?>
    <div class="lp-content-card ih-quick" id="kurz-fazit">
      <h2 class="lp-section-title"><?php echo esc_html($title); ?></h2>
      <div class="lp-grid lp-grid--2 ih-quick__grid">
        <?php foreach ($cards as $card):
          $t = (string)($card['title'] ?? '');
          $txt = (string)($card['text'] ?? '');
          if ($t === '' && $txt === '') continue;
        ?>
          <div class="trust-card ih-quick__card">
            <?php if ($t !== ''): ?><strong><?php echo esc_html($t); ?></strong><?php endif; ?>
            <?php if ($txt !== ''): ?><p><?php echo esc_html($txt); ?></p><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
    return (string)ob_get_clean();
  }
}

/** --- Sections (cards + checklist + inline InfoBox + Fazit-CTA) --- */
if (!function_exists('ih_render_sections')) {
  function ih_render_sections(array $c): string {
    $sections = isset($c['content']) && is_array($c['content']) ? $c['content'] : array();
    if (empty($sections)) return '';

    ob_start();
    foreach ($sections as $sec):
      if (!is_array($sec)) continue;

      $anchor = (string)($sec['anchor'] ?? '');
      $title  = (string)($sec['title'] ?? '');
      $intro_html = (string)($sec['intro_html'] ?? '');
      $geo_html   = (string)($sec['geo_html'] ?? '');
      $note_html  = (string)($sec['note_html'] ?? '');

      $grid_cards = isset($sec['grid_cards']) && is_array($sec['grid_cards']) ? $sec['grid_cards'] : array();
      $items      = isset($sec['items']) && is_array($sec['items']) ? $sec['items'] : array();
      $checklist  = isset($sec['checklist']) && is_array($sec['checklist']) ? $sec['checklist'] : array();

      // NEW: inline info_box per section (Enterprise)
      $info_box   = isset($sec['info_box']) && is_array($sec['info_box']) ? $sec['info_box'] : array();

      // Fazit CTA Box (dein "cta_box" im JSON)
      $cta_box    = isset($sec['cta_box']) && is_array($sec['cta_box']) ? $sec['cta_box'] : array();
    ?>
      <div class="lp-content-card ih-section"<?php echo $anchor !== '' ? ' id="' . esc_attr($anchor) . '"' : ''; ?>>
        <?php if ($title !== ''): ?>
          <h2 class="lp-section-title"><?php echo esc_html($title); ?></h2>
        <?php endif; ?>

        <?php if ($intro_html !== ''): ?>
          <div class="ih-section__intro"><?php echo wp_kses_post($intro_html); ?></div>
        <?php endif; ?>

        <?php if (!empty($grid_cards)): ?>
          <div class="lp-grid lp-grid--2 ih-cards">
            <?php foreach ($grid_cards as $gc):
              if (!is_array($gc)) continue;
              $ct = (string)($gc['title'] ?? '');
              $tx = (string)($gc['text'] ?? '');
              $bul = isset($gc['bullets']) && is_array($gc['bullets']) ? $gc['bullets'] : array();
            ?>
              <div class="trust-card ih-card">
                <?php if ($ct !== ''): ?><strong><?php echo esc_html($ct); ?></strong><?php endif; ?>
                <?php if ($tx !== ''): ?><p><?php echo esc_html($tx); ?></p><?php endif; ?>

                <?php if (!empty($bul)): ?>
                  <!-- IMPORTANT: no manual "•/✅" to avoid duplicates -->
                  <div class="ih-bullets-wrap">
                    <?php echo tw_render_ul_plain($bul, 'ih-bullets'); ?>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($items)): ?>
          <div class="ih-items">
            <?php foreach ($items as $it):
              if (!is_array($it)) continue;
              $h = (string)($it['heading'] ?? '');
              $t = (string)($it['text'] ?? '');
            ?>
              <?php if ($h !== ''): ?><h3><?php echo esc_html($h); ?></h3><?php endif; ?>
              <?php if ($t !== ''): ?><p><?php echo esc_html($t); ?></p><?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($note_html !== ''): ?>
          <div class="ih-note"><?php echo wp_kses_post($note_html); ?></div>
        <?php endif; ?>

        <?php
          // Inline InfoBox (Enterprise)
          if (!empty($info_box)) {
            echo '<div class="ih-infobox-inline">' . tw_render_infobox($info_box, 'infohub') . '</div>';
          }
        ?>

        <?php if (!empty($checklist)): ?>
          <div class="ih-checklist">
            <!-- IMPORTANT: no manual "✅" to avoid duplicates -->
            <?php echo tw_render_ul_plain($checklist, 'ih-checklist__ul'); ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($cta_box)):
          $ct = (string)($cta_box['title'] ?? '');
          $tx = (string)($cta_box['text'] ?? '');
          $btns = isset($cta_box['buttons']) && is_array($cta_box['buttons']) ? $cta_box['buttons'] : array();
        ?>
          <div class="tw-inquiry ih-cta">
            <div class="tw-inquiry__card">
              <?php if ($ct !== ''): ?><h3 class="tw-inquiry__title"><?php echo esc_html($ct); ?></h3><?php endif; ?>
              <?php if ($tx !== ''): ?><p class="tw-inquiry__text"><?php echo esc_html($tx); ?></p><?php endif; ?>

              <?php if (isset($btns['primary']['href'], $btns['primary']['label'])): ?>
                <a class="tw-inquiry__btn tw-inquiry__btn--call" href="<?php echo esc_url((string)$btns['primary']['href']); ?>">
                  <?php echo esc_html((string)$btns['primary']['label']); ?>
                </a>
              <?php endif; ?>
              <?php if (isset($btns['secondary']['href'], $btns['secondary']['label'])): ?>
                <a class="tw-inquiry__btn tw-inquiry__btn--wa" href="<?php echo esc_url((string)$btns['secondary']['href']); ?>" target="_blank" rel="noopener">
                  <?php echo esc_html((string)$btns['secondary']['label']); ?>
                </a>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($geo_html !== ''): ?>
          <div class="ih-geo"><?php echo wp_kses_post($geo_html); ?></div>
        <?php endif; ?>
      </div>
    <?php
    endforeach;

    return (string)ob_get_clean();
  }
}

if (!function_exists('ih_render_related')) {
  function ih_render_related(array $c): string {
    $r = isset($c['related']) && is_array($c['related']) ? $c['related'] : array();
    $items = isset($r['items']) && is_array($r['items']) ? $r['items'] : array();
    if (empty($items)) return '';

    $title = (string)($r['title'] ?? 'Passende Inhalte');

    ob_start(); ?>
    <div class="lp-content-card ih-related">
      <h2 class="lp-section-title"><?php echo esc_html($title); ?></h2>
      <div class="lp-grid lp-grid--2">
        <?php foreach ($items as $it):
          if (!is_array($it)) continue;
          $t = (string)($it['title'] ?? '');
          $href = (string)($it['href'] ?? '');
          if ($t === '' || $href === '') continue;
        ?>
          <a class="trust-card ih-related__item" href="<?php echo esc_url($href); ?>">
            <strong><?php echo esc_html($t); ?></strong>
            <p>Weiterlesen →</p>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php
    return (string)ob_get_clean();
  }
}

if (!function_exists('ih_render_faq')) {
  function ih_render_faq(array $c): string {
    $f = isset($c['faq']) && is_array($c['faq']) ? $c['faq'] : array();
    $items = isset($f['items']) && is_array($f['items']) ? $f['items'] : array();
    if (empty($items)) return '';

    $title = (string)($f['title'] ?? 'FAQ');

    ob_start(); ?>
    <div class="lp-content-card ih-faq" id="faq">
      <h2 class="lp-section-title"><?php echo esc_html($title); ?></h2>

      <div class="lp-faq__accordion-wrap">
        <?php if (shortcode_exists('accordion') && shortcode_exists('accordion-item')): ?>
          <?php
            $acc = "[accordion]";
            foreach ($items as $it) {
              $q = esc_attr((string)($it['question'] ?? ''));
              $a_html = wp_kses_post((string)($it['answer_html'] ?? ''));
              if ($q === '' || $a_html === '') continue;
              $acc .= '[accordion-item title="' . $q . '"]' . $a_html . '[/accordion-item]';
            }
            $acc .= "[/accordion]";
            echo do_shortcode($acc);
          ?>
        <?php else: ?>
          <?php foreach ($items as $it): ?>
            <?php $q = (string)($it['question'] ?? ''); ?>
            <?php $a_html = wp_kses_post((string)($it['answer_html'] ?? '')); ?>
            <?php if ($q === '' || $a_html === '') continue; ?>
            <details>
              <summary><?php echo esc_html($q); ?></summary>
              <div class="lp-faq__answer"><?php echo $a_html; ?></div>
            </details>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php
    return (string)ob_get_clean();
  }
}

/** =========================================================
 *  INFOHUB SHORTCODES
 *  ========================================================= */

if (!function_exists('ih_sc_infohub_page')) {
  function ih_sc_infohub_page($atts): string {
    $atts = shortcode_atts(array('id' => ''), $atts, 'lp_infohub_page');
    $id = sanitize_key((string)$atts['id']);
    if ($id === '') return '';

    $c = ih_get_content($id);
    if (empty($c)) {
      if (current_user_can('manage_options')) {
        return '<div style="padding:12px;border:1px solid #f00;">InfoHub: Kein JSON gefunden. Erwartet: <code>&lt;script id="ih-data-' . esc_html($id) . '" type="application/json"&gt;...&lt;/script&gt;</code></div>';
      }
      return '';
    }

    $meta = $c['meta'] ?? array();
    $h1 = (string)($meta['h1'] ?? '');
    $lead = (string)($meta['lead'] ?? '');
    $reading = (string)($meta['reading_time'] ?? '');
    $updated = (string)($meta['last_updated'] ?? '');

    $hero = $c['hero'] ?? array();
    $badge = (string)($hero['badge'] ?? '');
    $benefits = (isset($hero['benefits']) && is_array($hero['benefits'])) ? $hero['benefits'] : array();
    $cta = (isset($hero['cta']) && is_array($hero['cta'])) ? $hero['cta'] : array();

    ob_start(); ?>
    <div class="lp-shell lp-infohub lp-kueche">

      <div class="lp-content-card ih-hero">
        <?php if ($badge !== ''): ?>
          <span class="tw-inquiry__badge"><?php echo esc_html($badge); ?></span>
        <?php endif; ?>

        <?php if ($h1 !== ''): ?>
          <h1 class="lp-hero__title"><?php echo esc_html($h1); ?></h1>
        <?php endif; ?>

        <div class="ih-meta">
          <?php if ($reading !== ''): ?><span>⏱ <?php echo esc_html($reading); ?></span><?php endif; ?>
          <?php if ($updated !== ''): ?><span>🗓 Aktualisiert: <?php echo esc_html($updated); ?></span><?php endif; ?>
        </div>

        <?php if ($lead !== ''): ?>
          <p class="lp-hero__intro"><?php echo esc_html($lead); ?></p>
        <?php endif; ?>

        <?php if (!empty($benefits)): ?>
          <!-- IMPORTANT: no manual "✅" to avoid duplicates -->
          <div class="ih-benefits-wrap">
            <?php echo tw_render_ul_plain($benefits, 'ih-benefits'); ?>
          </div>
        <?php endif; ?>

        <div class="ih-hero__ctas">
          <?php if (isset($cta['primary']['href'], $cta['primary']['label'])): ?>
            <a class="tw-inquiry__btn tw-inquiry__btn--call" href="<?php echo esc_url((string)$cta['primary']['href']); ?>">
              <?php echo esc_html((string)$cta['primary']['label']); ?>
            </a>
          <?php endif; ?>
          <?php if (isset($cta['secondary']['href'], $cta['secondary']['label'])): ?>
            <a class="tw-inquiry__btn tw-inquiry__btn--wa" href="<?php echo esc_url((string)$cta['secondary']['href']); ?>" target="_blank" rel="noopener">
              <?php echo esc_html((string)$cta['secondary']['label']); ?>
            </a>
          <?php endif; ?>
        </div>
      </div>

      <?php echo ih_render_toc($c); ?>
      <?php echo ih_render_quick_fazit($c); ?>
		<?php
  if (function_exists('tw_render_infobox_insurance')) {
    echo tw_render_infobox_insurance($c, 'infohub');
  }
?>
      <?php echo ih_render_sections($c); ?>
      <?php echo ih_render_related($c); ?>
      <?php echo ih_render_faq($c); ?>

    </div>
    <?php
    return (string)ob_get_clean();
  }
}

if (!function_exists('ih_sc_infohub_schema')) {
  function ih_sc_infohub_schema($atts): string {
    $atts = shortcode_atts(array('id' => ''), $atts, 'lp_infohub_schema');
    $id = sanitize_key((string)$atts['id']);
    if ($id === '') return '';

    $c = ih_get_content($id);
    if (empty($c)) return '';

    $s = $c['schema'] ?? array();
    $headline = (string)($s['headline'] ?? '');
    $about = (string)($s['about'] ?? '');
    $url = (string)($s['page_url'] ?? '');

    $biz = isset($s['business']) && is_array($s['business']) ? $s['business'] : array();

    $faq_items = (isset($c['faq']['items']) && is_array($c['faq']['items'])) ? $c['faq']['items'] : array();
    $mainEntity = array();
    foreach ($faq_items as $it) {
      $q = wp_strip_all_tags((string)($it['question'] ?? ''));
      $a = wp_strip_all_tags((string)($it['answer_html'] ?? ''));
      if ($q === '' || $a === '') continue;
      $mainEntity[] = array(
        '@type' => 'Question',
        'name'  => $q,
        'acceptedAnswer' => array('@type' => 'Answer', 'text' => $a),
      );
    }

    $graph = array();

    $graph[] = array(
      '@type' => 'Article',
      'headline' => $headline,
      'about' => $about,
      'mainEntityOfPage' => $url,
      'author' => array('@type' => 'Organization', 'name' => (string)($biz['name'] ?? '')),
      'publisher' => array('@type' => 'Organization', 'name' => (string)($biz['name'] ?? '')),
    );

    if (!empty($mainEntity)) {
      $graph[] = array('@type' => 'FAQPage', 'mainEntity' => $mainEntity);
    }

    $payload = array('@context' => 'https://schema.org', '@graph' => $graph);

    return '<script type="application/ld+json">' . wp_json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . '</script>';
  }
}

add_shortcode('lp_infohub_page', 'ih_sc_infohub_page');
add_shortcode('lp_infohub_schema', 'ih_sc_infohub_schema');