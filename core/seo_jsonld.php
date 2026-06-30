<?php
/**
 * SEO JSON-LD helpers (schema.org).
 *
 * Mỗi function trả về một chuỗi HTML <script type="application/ld+json">…</script> sẵn sàng in.
 * Mọi field rỗng sẽ được loại bỏ trước khi encode.
 */

if (!function_exists('seo_jsonld_clean')) {
    function seo_jsonld_clean(array $arr): array {
        $out = [];
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $v = seo_jsonld_clean($v);
                if (!empty($v)) $out[$k] = $v;
            } else {
                if ($v === '' || $v === null) continue;
                $out[$k] = $v;
            }
        }
        return $out;
    }
}

if (!function_exists('seo_jsonld_render')) {
    function seo_jsonld_render(array $data): string {
        $data = seo_jsonld_clean($data);
        if (empty($data)) return '';
        return '<script type="application/ld+json">'
            . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . '</script>';
    }
}

if (!function_exists('seo_abs_url')) {
    function seo_abs_url(?string $url, string $baseUrl = ''): string {
        $u = trim((string)$url);
        if ($u === '') return '';
        if (preg_match('#^https?://#i', $u)) return $u;
        if (strpos($u, '//') === 0) return 'https:' . $u;
        $base = rtrim((string)$baseUrl, '/');
        return $base . '/' . ltrim($u, '/');
    }
}

if (!function_exists('seo_publisher_node')) {
    function seo_publisher_node(string $name, string $logoUrl): array {
        $node = [
            '@type' => 'Organization',
            'name'  => $name,
        ];
        if ($logoUrl !== '') {
            $node['logo'] = [
                '@type' => 'ImageObject',
                'url'   => $logoUrl,
            ];
        }
        return $node;
    }
}

/* =====================================================================
 * Organization (sitewide)
 * ===================================================================== */
if (!function_exists('seo_jsonld_organization')) {
    function seo_jsonld_organization(array $opts = []): string {
        $name    = (string)($opts['name'] ?? '');
        $url     = (string)($opts['url'] ?? '');
        $logo    = (string)($opts['logo'] ?? '');
        $sameAs  = (array)($opts['sameAs'] ?? []);
        $phone   = (string)($opts['phone'] ?? '');
        $address = (array)($opts['address'] ?? []);

        $data = [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => $name,
            'url'      => $url,
            'logo'     => $logo,
            'sameAs'   => $sameAs,
        ];
        if ($phone !== '') {
            $data['contactPoint'] = [
                '@type' => 'ContactPoint',
                'telephone' => $phone,
                'contactType' => 'customer service',
                'areaServed' => 'VN',
                'availableLanguage' => ['vi', 'en'],
            ];
        }
        if (!empty($address)) {
            $data['address'] = array_merge(['@type' => 'PostalAddress'], $address);
        }
        return seo_jsonld_render($data);
    }
}

/* =====================================================================
 * WebSite (sitewide, có hỗ trợ SearchAction)
 * ===================================================================== */
if (!function_exists('seo_jsonld_website')) {
    function seo_jsonld_website(string $name, string $url): string {
        return seo_jsonld_render([
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => $name,
            'url'      => $url,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => rtrim($url, '/') . '/search/{search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ]);
    }
}

/* =====================================================================
 * BreadcrumbList
 * Items: [ ['name'=>..., 'url'=>...], ... ]
 * ===================================================================== */
if (!function_exists('seo_jsonld_breadcrumb')) {
    function seo_jsonld_breadcrumb(array $items): string {
        $list = [];
        $i = 1;
        foreach ($items as $it) {
            $name = trim((string)($it['name'] ?? ''));
            $url  = trim((string)($it['url'] ?? ''));
            if ($name === '') continue;
            $entry = [
                '@type'    => 'ListItem',
                'position' => $i++,
                'name'     => $name,
            ];
            if ($url !== '') $entry['item'] = $url;
            $list[] = $entry;
        }
        if (empty($list)) return '';
        return seo_jsonld_render([
            '@context' => 'https://schema.org',
            '@type'    => 'BreadcrumbList',
            'itemListElement' => $list,
        ]);
    }
}

/* =====================================================================
 * NewsArticle / BlogPosting / Article
 * ===================================================================== */
if (!function_exists('seo_jsonld_article')) {
    function seo_jsonld_article(array $a): string {
        $type = (string)($a['type'] ?? 'Article'); // NewsArticle | BlogPosting | Article
        $publisher = (array)($a['publisher'] ?? []);
        $data = [
            '@context'         => 'https://schema.org',
            '@type'            => $type,
            'headline'         => (string)($a['headline'] ?? ''),
            'description'      => (string)($a['description'] ?? ''),
            'image'            => !empty($a['image']) ? (array)$a['image'] : [],
            'datePublished'    => (string)($a['datePublished'] ?? ''),
            'dateModified'     => (string)($a['dateModified'] ?? ($a['datePublished'] ?? '')),
            'articleSection'   => (string)($a['articleSection'] ?? ''),
            'inLanguage'       => (string)($a['inLanguage'] ?? 'vi'),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => (string)($a['url'] ?? ''),
            ],
            'publisher' => seo_publisher_node(
                (string)($publisher['name'] ?? ''),
                (string)($publisher['logo'] ?? '')
            ),
        ];
        if (!empty($a['author'])) {
            $data['author'] = [
                '@type' => 'Person',
                'name'  => (string)$a['author'],
            ];
        }
        if (!empty($a['keywords']) && is_array($a['keywords'])) {
            $data['keywords'] = implode(', ', array_filter(array_map('trim', $a['keywords'])));
        }
        return seo_jsonld_render($data);
    }
}

/* =====================================================================
 * Product (cho view-product)
 * ===================================================================== */
if (!function_exists('seo_jsonld_product')) {
    function seo_jsonld_product(array $p): string {
        $publisher = (array)($p['publisher'] ?? []);
        $offers = (array)($p['offers'] ?? []);
        $images = !empty($p['image']) ? (array)$p['image'] : [];

        $data = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => (string)($p['name'] ?? ''),
            'description' => (string)($p['description'] ?? ''),
            'image'       => $images,
            'sku'         => (string)($p['sku'] ?? ''),
            'mpn'         => (string)($p['mpn'] ?? ''),
            'url'         => (string)($p['url'] ?? ''),
        ];

        $brand = trim((string)($p['brand'] ?? ''));
        if ($brand !== '') {
            $data['brand'] = ['@type' => 'Brand', 'name' => $brand];
        }

        if (!empty($offers)) {
            $price = (float)($offers['price'] ?? 0);
            $offerNode = [
                '@type'         => 'Offer',
                'priceCurrency' => (string)($offers['currency'] ?? 'VND'),
                'price'         => $price > 0 ? (string)$price : '',
                'availability'  => (string)($offers['availability'] ?? 'https://schema.org/InStock'),
                'url'           => (string)($offers['url'] ?? ($p['url'] ?? '')),
                'priceValidUntil' => (string)($offers['priceValidUntil'] ?? ''),
            ];
            if (!empty($publisher['name'])) {
                $offerNode['seller'] = ['@type' => 'Organization', 'name' => (string)$publisher['name']];
            }
            $data['offers'] = $offerNode;
        }

        if (!empty($p['aggregateRating'])) {
            $ar = (array)$p['aggregateRating'];
            $ratingValue = (float)($ar['ratingValue'] ?? 0);
            $reviewCount = (int)($ar['reviewCount'] ?? 0);
            if ($ratingValue > 0 && $reviewCount > 0) {
                $data['aggregateRating'] = [
                    '@type'       => 'AggregateRating',
                    'ratingValue' => (string)$ratingValue,
                    'reviewCount' => (string)$reviewCount,
                    'bestRating'  => '5',
                    'worstRating' => '1',
                ];
            }
        }

        return seo_jsonld_render($data);
    }
}
