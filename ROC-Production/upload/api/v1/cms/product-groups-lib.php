<?php
/**
 * Run On Console — the two main product categories (defaults).
 *
 * PC Hardware and Gaming Hardware, shown at /category/{slug}/ and on /category/. These are the
 * defaults; CMS > Product categories > Main categories saves the names, descriptions, pictures and
 * which category belongs to PC Hardware (cms_settings product_groups). Categories not listed under
 * pc-hardware belong to gaming-hardware. Used by product-render.php and api/v1/cms/catalog-admin.php.
 */
if (defined('ROC_PRODUCT_GROUPS')) return;

const ROC_PRODUCT_GROUPS = [
    'pc-hardware' => ['name' => 'PC Hardware', 'members' => ['gpu', 'cpu', 'ram', 'cases', 'monitors', 'speakers'],
        'description' => 'Graphics cards, processors, memory, PC cases, monitors and speakers for your gaming PC, each with key details and a direct Amazon link.'],
    'gaming-hardware' => ['name' => 'Gaming Hardware', 'members' => null,
        'description' => 'Keyboards, mice, headsets, gaming chairs, handhelds and gaming laptops, each with key details and a direct Amazon link.'],
];
