<?php

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;

class MarkdownService
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $config = [
            'heading_permalink' => [
                'html_class' => 'heading-permalink',
                'id_prefix' => '',
                'apply_id_to_heading' => true,
                'heading_class' => '',
                'fragment_prefix' => '',
                'insert' => 'before',
                'min_heading_level' => 2,
                'max_heading_level' => 3,
                'title' => 'Permalink',
                'symbol' => '#',
                'aria_hidden' => true,
            ],
        ];

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new HeadingPermalinkExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Markdown → HTML 変換
     */
    public function toHtml(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }

    /**
     * Markdownから目次(TOC)を生成
     * h2/h3を抽出して階層的な目次HTMLを返す
     */
    public function generateToc(string $markdown): string
    {
        $document = $this->converter->convert($markdown);
        $html = $document->getContent();

        // HTMLからh2/h3を正規表現で抽出
        preg_match_all('/<h([23])[^>]*id="([^"]*)"[^>]*>.*?<a[^>]*>.*?<\/a>\s*(.*?)<\/h[23]>/s', $html, $matches, PREG_SET_ORDER);

        if (empty($matches)) {
            return '';
        }

        $toc = '<nav class="toc"><ol>';
        $currentLevel = 2;

        foreach ($matches as $match) {
            $level = (int) $match[1];
            $id = $match[2];
            $text = strip_tags($match[3]);

            if ($level > $currentLevel) {
                $toc .= '<ol>';
            } elseif ($level < $currentLevel) {
                $toc .= '</li></ol>';
            } else {
                if ($currentLevel === $level && $match !== $matches[0]) {
                    $toc .= '</li>';
                }
            }

            $toc .= '<li><a href="#' . $id . '">' . e($text) . '</a>';
            $currentLevel = $level;
        }

        $toc .= '</li></ol></nav>';

        return $toc;
    }
}
