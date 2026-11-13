<?php

namespace App\Services\Presentation;

use RuntimeException;
use ZipArchive;

/**
 * Replaces the slides of an open .pptx with a new list, keeping the template's theme, masters
 * and layouts. Each new slide reuses the relationships (its layout) of the template slide it
 * was made from, and the presentation's slide list, relationships and content types are
 * rewritten to match.
 */
class SlidePackage
{
    private const SLIDE_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide';

    private const SLIDE_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.presentationml.slide+xml';

    public function __construct(private readonly ZipArchive $archive) {}

    /**
     * XML of template slide number $number (by file name).
     */
    public function templateSlide(int $number): string
    {
        return $this->read("ppt/slides/slide{$number}.xml");
    }

    public function templateRelationships(int $number): string
    {
        return $this->read("ppt/slides/_rels/slide{$number}.xml.rels");
    }

    /**
     * @param  list<array{xml: string, rels: string}>  $slides
     */
    public function replaceSlides(array $slides): void
    {
        for ($index = $this->archive->numFiles - 1; $index >= 0; $index--) {
            $name = (string) $this->archive->getNameIndex($index);

            if (preg_match('#^ppt/slides/(_rels/)?slide\d+\.xml(\.rels)?$#', $name) === 1) {
                $this->archive->deleteIndex($index);
            }
        }

        $relationships = '';
        $slideIds = '';
        $overrides = '';

        foreach (array_values($slides) as $index => $slide) {
            $number = $index + 1;
            $this->archive->addFromString("ppt/slides/slide{$number}.xml", $slide['xml']);
            $this->archive->addFromString("ppt/slides/_rels/slide{$number}.xml.rels", $slide['rels']);
            $relationships .= '<Relationship Id="rIdSlide'.$number.'" Type="'.self::SLIDE_TYPE.'" Target="slides/slide'.$number.'.xml"/>';
            $slideIds .= '<p:sldId id="'.(255 + $number).'" r:id="rIdSlide'.$number.'"/>';
            $overrides .= '<Override PartName="/ppt/slides/slide'.$number.'.xml" ContentType="'.self::SLIDE_CONTENT_TYPE.'"/>';
        }

        $presentationRels = (string) preg_replace('#<Relationship [^>]*Type="'.preg_quote(self::SLIDE_TYPE, '#').'"[^>]*/>#', '', $this->read('ppt/_rels/presentation.xml.rels'));
        $this->archive->addFromString('ppt/_rels/presentation.xml.rels', str_replace('</Relationships>', $relationships.'</Relationships>', $presentationRels));

        $presentation = (string) preg_replace('#<p:sldIdLst>.*?</p:sldIdLst>#s', '<p:sldIdLst>'.$slideIds.'</p:sldIdLst>', $this->read('ppt/presentation.xml'));
        $this->archive->addFromString('ppt/presentation.xml', $presentation);

        $types = (string) preg_replace('#<Override PartName="/ppt/slides/slide\d+\.xml"[^>]*/>#', '', $this->read('[Content_Types].xml'));
        $this->archive->addFromString('[Content_Types].xml', str_replace('</Types>', $overrides.'</Types>', $types));

        $app = $this->archive->getFromName('docProps/app.xml');

        if (is_string($app)) {
            $this->archive->addFromString('docProps/app.xml', (string) preg_replace('#<Slides>\d+</Slides>#', '<Slides>'.count($slides).'</Slides>', $app));
        }
    }

    private function read(string $name): string
    {
        $contents = $this->archive->getFromName($name);

        if (! is_string($contents)) {
            throw new RuntimeException("Bagian template {$name} tidak ditemukan.");
        }

        return $contents;
    }
}
