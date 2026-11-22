<?php

/**
 * Demo content for tests/dev-site.sh: a CKEditor "Body" field, a "Blog" channel and two posts.
 *   php craft-seed.php /app [--posts]
 */

declare(strict_types=1);

use craft\ckeditor\Field as CkeField;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;

$root = $argv[1] ?? '/app';
require $root . '/bootstrap.php';
/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

$fail = static function (string $what, $model): never {
    fwrite(STDERR, "Could not save $what: " . json_encode($model->getErrors()) . "\n");
    exit(1);
};

$fields = Craft::$app->getFields();
$body = $fields->getFieldByHandle('body');
if (!$body) {
    $body = new CkeField([
        'name' => 'Body',
        'handle' => 'body',
        'toolbar' => ['heading', '|', 'bold', 'italic', 'link', '|', 'bulletedList', 'numberedList', 'blockQuote', '|', 'sourceEditing'],
        'headingLevels' => [2, 3],
    ]);
    $fields->saveField($body) || $fail('field', $body);
}

$entries = Craft::$app->getEntries();
$type = $entries->getEntryTypeByHandle('post');
if (!$type) {
    $type = new EntryType(['name' => 'Post', 'handle' => 'post']);
    $layout = new FieldLayout(['type' => Entry::class]);
    $tab = new FieldLayoutTab(['name' => 'Content', 'layout' => $layout]);
    $tab->setElements([new EntryTitleField(), new CustomField($body)]);
    $layout->setTabs([$tab]);
    $type->setFieldLayout($layout);
    $entries->saveEntryType($type) || $fail('entry type', $type);
}

$section = $entries->getSectionByHandle('blog');
if (!$section) {
    $site = Craft::$app->getSites()->getPrimarySite();
    $section = new Section([
        'name' => 'Blog',
        'handle' => 'blog',
        'type' => Section::TYPE_CHANNEL,
        'siteSettings' => [
            new Section_SiteSettings([
                'siteId' => $site->id,
                'enabledByDefault' => true,
                'hasUrls' => true,
                'uriFormat' => 'blog/{slug}',
                'template' => 'blog/_entry.twig',
            ]),
        ],
    ]);
    $section->setEntryTypes([$type]);
    $entries->saveSection($section) || $fail('section', $section);
}

$posts = [
    [
        'title' => 'A tiny workbench for small spaces',
        'slug' => 'a-tiny-workbench-for-small-spaces',
        'body' => '<p>You don\'t need a garage to make things. My whole workshop fits on a folding table by the window.</p><h2>What lives on the table</h2><p>A cutting mat, a small 3D printer, a tray of tools and a lamp. Everything else goes in one box underneath.</p>',
        'date' => '-4 days',
    ],
    [
        'title' => 'My five-minute desk reset',
        'slug' => 'my-five-minute-desk-reset',
        'body' => '<p>Every Friday afternoon I spend five minutes resetting my desk. It sounds silly, but Monday morning feels completely different.</p><h2>Clear, sort, add one thing</h2><p>Clear everything off, put back only what I used this week, and add one small thing that makes the space feel mine.</p>',
        'date' => '-9 days',
    ],
];
foreach ($posts as $p) {
    if (Entry::find()->section('blog')->slug($p['slug'])->status(null)->exists()) {
        continue;
    }
    $entry = new Entry([
        'sectionId' => $section->id,
        'typeId' => $type->id,
        'title' => $p['title'],
        'slug' => $p['slug'],
        'authorId' => 1,
        'postDate' => new DateTime($p['date']),
    ]);
    $entry->setFieldValue('body', $p['body']);
    Craft::$app->getElements()->saveElement($entry) || $fail('entry', $entry);
}

echo "Seeded: Body field, Blog channel, " . Entry::find()->section('blog')->count() . " posts\n";
