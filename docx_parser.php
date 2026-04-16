<?php
use PhpOffice\PhpWord\IOFactory;

function extractTextFromDocx($filePath) {
    $phpWord = IOFactory::load($filePath);
    $text = "";

    foreach ($phpWord->getSections() as $section) {
        foreach ($section->getElements() as $element) {
            if (method_exists($element, 'getText')) {
                $text .= $element->getText() . "\n";
            }
        }
    }

    return trim($text);
}
