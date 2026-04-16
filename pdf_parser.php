<?php

require_once __DIR__ . '/vendor/autoload.php';

function extractTextFromPDF($filePath) {
    $parser = new \Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($filePath);

    return $pdf->getText();
}