<?php

namespace Dan\Larascrape\Console;

use Dan\AiCrawler\Support\HtmlSourceDownloader;
use Illuminate\Console\Command;
use Dan\Core\Helpers\RegEx;
use Dan\AiCrawler\Support\AiConfig;
use Dan\AiCrawler\Support\AiCrawler;
use Dan\AiCrawler\Support\Source;
use Dan\AiCrawler\Support\Exceptions\SourceNotFoundException;

/**
 * Run BFS on URL and provide plenty of options to the user for output.
 *
 * @package AiCrawler\Commands
 * @todo Needs rewrite.
 */
class BfsCommand extends Command {

    protected $config;

    /**
     * Setup our Commmand
     */
    protected function configure() {
        $this->config = new AiConfig();

        $this
            ->setName('dom:bfs')
            ->setHelp("e.g. php crawl dom:bfs http://www.example.com/ -da --only=\"div,p\" \t\t// Outputs all details on div and p elements.")
            ->setDescription('Get details about the DOM using breadth-first search.')
            ->addArgument(
                'url',
                InputArgument::OPTIONAL,
                'Enter a URL to inspect.',
                'http://www.example.com/'
            )
            ->addOption(
                'details',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Try any combination of all(a), parents(p), children(c), siblings(s), depth(d), words(w), sentences(sn), paragraphs(pg), text(t) or html(h).'
            )
            ->addOption(
                'stop',
                's',
                InputOption::VALUE_OPTIONAL,
                'When should we stop? e.g. 3 (shows nodes up to 3 levels deep)'
            )
            ->addOption(
                'only',
                'o',
                InputOption::VALUE_OPTIONAL,
                'Only traverse certain tags? e.g. html (auto included), body (auto included), header, footer, div (span, script, etc...are ignored)'
            )
            ->addOption(
                'except',
                'e',
                InputOption::VALUE_OPTIONAL,
                'Search all tags except these and their children? e.g. span, script (omits these tags)'
            );
    }

    /**
     * Output some nodes BFS style.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function handle() {
        /**
         * What to search?
         */
        $url = $this->argument('url');
        if ($url == 'http://www.example.com/')
            $this->info("A URL argument was not provided, http://www.example.com will be used.");

        /**
         * What to display?
         */
        $extra = [];
        $extra['details'] = explode(",", RegEx::removeWhitespace($this->option('details')));
        $extra['only'] = !is_null($this->option('only'))
            ? array_merge(explode(",", RegEx::removeWhitespace($this->option('only'))), ['html', 'body'])
            : $this->option('only');
        $extra['except'] = !is_null($this->option('except'))
            ? explode(",", RegEx::removeWhitespace($this->option('except')))
            : $this->option('except');

        /**
         * How deep should we go?
         */
        $stop = $this->option('stop');

        /**
         * Setup a crawler and output
         */
        try {
            $html = HtmlSourceDownloader::get($url);
            $crawler = new AiCrawler($html->getSource());
            $text = self::bfsOutput($crawler, $extra, $stop);
            $this->info($text);
        } catch (SourceNotFoundException $e) {
            $this->info("Unable to download the source with curl. ".$e->getMessage());
        } catch (\InvalidArgumentException $e) {
            $this->info("A crawler method was called with a bad argument. ".$e->getMessage());
        }
    }

    /**
     * Perform Bread-first Search on DomCrawler / Articrawl Node
     *
     * @param $node
     * @param array $extra
     * @param int $stop
     * @param int $depth
     * @return string
     */
    public static function bfsOutput($node, $extra = [], $stop = 100, $depth = 0) {
        /**
         * Setup our vars
         */
        $only = array_key_exists("only", $extra) && is_array($extra['only']) ? $extra['only'] : null;
        // only cannot be combined with except
        $except = is_null($only) && array_key_exists("except", $extra) && is_array($extra['except']) ? $extra['except'] : null;

        /**
         * Conditions for further traversal
         */
        $text = "";
        if ( (is_array($only) && in_array($node->nodeName(), $only))
            || (is_array($except) && !in_array($node->nodeName(), $except))
            || ($only == null && $except == null)
        ) {
            $text = self::output($node, $extra, $depth, $text);
            /**
             * BFS Recursion
             */
            if (!is_numeric($stop) || $depth < $stop) {
                $node->children()->each(function ($n, $i) use ($extra, $stop, $depth, &$text) {
                    $text .= self::bfsOutput($n, $extra, $stop, $depth + 1);
                });
            }
        }
        return $text;
    }

    /**
     * @param $node
     * @param $extra
     * @param $depth
     * @param $text
     * @return string
     */
    private static function output($node, $extra, $depth, $text)
    {
        /**
         * Text Output
         */
        $tabs = str_repeat("   ", $depth);
        $text .= $tabs.$node->nodeName();
        $show = $extra['details'];
        $numbers = [];

        if (count(array_intersect(['parents', 'p', 'all', 'a'], $show)))
            $numbers[] = "Parents(" . $node->parents()->count() . ")";
        if (count(array_intersect(['children', 'c', 'all', 'a'], $show)))
            $numbers[] = "Children(" . $node->children()->count() . ")";
        if (count(array_intersect(['siblings', 's', 'all', 'a'], $show)))
            $numbers[] = "Siblings(" . $node->siblings()->count() . ")";
        if (count(array_intersect(['depth', 'd', 'all', 'a'], $show)))
            $numbers[] = "Depth(" . $depth . ")";
        if (count(array_intersect(['words', 'w', 'all', 'a'], $show)))
            $numbers[] = "Words(" . $node->numWords() . ")";
        if (count(array_intersect(['sentences', 'sn', 'all', 'a'], $show)))
            $numbers[] = "Sentences(" . $node->numSentences() . ")";
        if (count(array_intersect(['paragraphs', 'pg', 'all', 'a'], $show)))
            $numbers[] = "Paragraphs(" . $node->numParagraphs() . ")";

        $text .= count($numbers) ? "- " : "";
        $text .= implode(", ", $numbers);
        if (count(array_intersect(['text', 't', 'all', 'a'], $show)))
            $text .= "\n".$tabs."\t\tTEXT: ".substr(RegEx::removeExtraneousWhitespace($node->text()), 0, 140);
        if (count(array_intersect(['html', 'h', 'all', 'a'], $show)))
            $text .= "\n".$tabs."\t\tHTML: ".substr(RegEx::removeExtraneousWhitespace($node->html()), 0, 140);
        $text .= "\n";
        return $text;
    }

}