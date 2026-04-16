<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use FPDF;

// --- FPDF CLASS DEFINITION ---
// We move this outside the controller or at the top so it can properly extend FPDF.
class LISTENING_PDF extends FPDF {
    public $topic_header;

    // We pass the topic through the constructor instead of using $GLOBALS
    function __construct($topic_header, $orientation = 'P', $unit = 'mm', $size = 'A4') {
        $this->topic_header = $topic_header;
        parent::__construct($orientation, $unit, $size);
    }
    
    function RoundedRect($x, $y, $w, $h, $r, $style = '', $angle = '1234') {
        $k = $this->k;
        $hp = $this->h;
        if($style=='F') $op='f';
        elseif($style=='FD' || $style=='DF') $op='B';
        else $op='S';
        $MyArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k));

        $xc = $x+$w-$r; $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k,($hp-$y)*$k));
        if (strpos($angle, '2')===false) $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$y)*$k));
        else $this->_arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);

        $xc = $x+$w-$r; $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
        if (strpos($angle, '3')===false) $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-($y+$h))*$k));
        else $this->_arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);

        $xc = $x+$r; $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
        if (strpos($angle, '4')===false) $this->_out(sprintf('%.2F %.2F l',$x*$k,($hp-($y+$h))*$k));
        else $this->_arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);

        $xc = $x+$r; $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k));
        if (strpos($angle, '1')===false) {
            $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$y)*$k));
            $this->_out(sprintf('%.2F %.2F l',($x+$r)*$k,($hp-$y)*$k));
        } else $this->_arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _arc($x1, $y1, $x2, $y2, $x3, $y3) {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k, 
            $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
    }

    function Header() {
        if ($this->PageNo() == 1) {
            $this->SetFillColor(79, 70, 229); 
            $this->Rect(0, 0, 210, 4, 'F');
        } else {
            $this->SetFont('Arial', 'B', 8);
            $this->SetTextColor(148, 163, 184); 
            $this->Cell(0, 10, utf8_decode($this->topic_header), 0, 1, 'R');
            $this->Line(10, 15, 200, 15);
        }
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(148, 163, 184); 
        $this->Cell(0, 10, 'EVRTEST.COM | Page ' . $this->PageNo() . ' / {nb}', 0, 0, 'C');
    }

    function DrawFlowArrow($x, $y) {
        $this->SetDrawColor(203, 213, 225);
        $this->SetLineWidth(0.6);
        $this->Line($x, $y, $x, $y + 6);
        $this->Line($x - 1.5, $y + 4.5, $x, $y + 6);
        $this->Line($x + 1.5, $y + 4.5, $x, $y + 6);
        $this->SetLineWidth(0.2); 
    }

    function CheckPageBreak($h) {
        if ($this->GetY() + $h > 270) {
            $this->AddPage();
            return true;
        }
        return false;
    }

    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if($nb > 0 and $s[$nb-1] == "\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while($i < $nb) {
            $c = $s[$i];
            if($c == "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if($c == ' ') $sep = $i;
            $l += $cw[$c];
            if($l > $wmax) {
                if($sep == -1) { if($i == $j) $i++; }
                else $i = $sep + 1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        }
        return $nl;
    }

    function WordWrap($text, $maxwidth) {
        $text = trim($text);
        if ($text === '') return 0;
        $words = explode(' ', $text);
        $lines = 1;
        $width = 0;
        foreach ($words as $word) {
            $wordwidth = $this->GetStringWidth($word . ' ');
            if ($width + $wordwidth <= $maxwidth) {
                $width += $wordwidth;
            } else {
                $width = $wordwidth;
                $lines++;
            }
        }
        return $lines;
    }
}


class DownloadTestsController extends Controller
{
    public function downloadListening($id)
{
    // 1. SECURITY & DOWNLOAD BALANCE CHECK
    $user = Auth::user();
    if (!$user) {
        abort(401, 'Access denied. Please log in.');
    }

    // Define monthly limits (should match your Dashboard logic)
    $maxDownloads = ($user->plan_type === 'pro') ? 100 : 5; // Example: 100 for Pro, 5 for Free
    $remaining = $maxDownloads - $user->download_attempts;

    // Check if user has at least 1 download left
    if ($remaining <= 0) {
        abort(403, 'You have reached your download limit for this month.');
    }

    // 2. FETCH DATA & OWNERSHIP CHECK
    $test = DB::table('practice_sessions')
        ->where('id', $id)
        ->where('user_id', $user->id)
        ->first();

    if (!$test) {
        abort(404, 'Test not found or you do not have permission to download it.');
    }

    // 3. INCREMENT DOWNLOAD ATTEMPTS
    // This reduces the $remaining count for the next request
    DB::table('users')->where('id', $user->id)->increment('download_attempts');

        // 4. DECODE DATA
        $questions = json_decode($test->questions, true) ?? [];
        $layout_json = json_decode($test->layout_frontent, true) ?? [];

        $layouts = [];
        if (!empty($layout_json)) {
            if (isset($layout_json[0]) && is_array($layout_json[0])) {
                $layouts = $layout_json; 
            } else {
                $layouts = [$layout_json]; 
            }
        }

        // 5. GROUP QUESTIONS
        $blocks = [];
        $current_subtype = null;
        $current_block = [];
        $topic = 'Listening Practice'; // Default

        foreach ($questions as $q) {
            $q_type = $q['question_type'] ?? '';
            $subtype = strtoupper($q['subtype'] ?? $q['completion_type'] ?? 'UNKNOWN');
            
            if ($subtype !== $current_subtype && $current_subtype !== null) {
                $blocks[] = [
                    'question_type' => $current_block[0]['question_type'] ?? '',
                    'subtype' => $current_subtype,
                    'questions' => $current_block
                ];
                $current_block = [];
            }
            $current_subtype = $subtype;
            $current_block[] = $q;
            $topic = $q['script_title'] ?? $topic;
        }

        if (!empty($current_block)) {
            $blocks[] = [
                'question_type' => $current_block[0]['question_type'] ?? '',
                'subtype' => $current_subtype,
                'questions' => $current_block
            ];
        }

        // 6. GENERATE PDF
        $pdf = new LISTENING_PDF($topic);
        $pdf->AliasNbPages();
        $pdf->AddPage();

        // --- PDF HEADER ---
        $pdf->SetFont('Arial', 'B', 20);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->Cell(140, 10, utf8_decode($topic), 0, 1, 'L');

        $pdf->SetFont('Arial', 'B', 10);
        $section = $test->section_number ?? "section 1";

        $pdf->SetTextColor(71, 85, 105); 
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(45, 6, "LISTENING SIMULATION", 0, 0, 'L');

        $pdf->SetTextColor(79, 70, 229); 
        $pdf->Cell(95, 6, "| Section " . strtoupper($section), 0, 1, 'L');

        $pdf->SetTextColor(30, 41, 59);

        // --- QR CODE LOGIC (Laravel Style) ---
        $access_token = bin2hex(random_bytes(16)); 
        DB::table('practice_sessions')->where('id', $id)->update(['access_token' => $access_token]);

        // Use Laravel's url() helper to generate absolute URLs based on .env
        $audio_player_url = url("/play.php?token=" . $access_token); 
        $qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($audio_player_url);
        
        $pdf->Image($qr_api_url, 165, 12, 30, 30, 'PNG');
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->SetXY(165, 42);
        $pdf->Cell(30, 5, "SCAN FOR AUDIO", 0, 1, 'C');
        $pdf->SetY(48);

        // ==========================================
        // DYNAMIC BLOCK RENDERING LOOP
        // ==========================================
        $layout_index = 0;

        foreach ($blocks as $block) {
            $b_type = $block['question_type'];
            $subtype = $block['subtype'];
            $b_questions = $block['questions'];

            $pdf->Ln(5);

            // 1. COMPLETION TYPES
            if ($b_type === 'completion' || in_array($subtype, ['FORM', 'TABLE', 'FLOW-CHART', 'FLOWCHART', 'NOTES', 'SUMMARY'])) {
                
                $layout_data = $layouts[$layout_index] ?? $layouts[0] ?? [];
                $structure = $layout_data['structure'] ?? [];
                $word_limit = $layout_data['word_limit_instruction'] ?? $test->word_limit_rule ?? '';
                $layout_index++;

                if (!empty($word_limit)) {
                    $pdf->SetFont('Arial', 'I', 10);
                    $pdf->SetTextColor(100, 116, 139);
                    $pdf->Cell(0, 6, utf8_decode("Instruction: Write " . $word_limit . " for each answer."), 0, 1);
                    $pdf->Ln(3);
                }

                if ($subtype === 'FORM' || $subtype === 'NOTES' || $subtype === 'SUMMARY') {
                    $pdf->SetDrawColor(203, 213, 225);
                    $pdf->SetLineWidth(0.5);
                    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
                    $pdf->Ln(5);

                    foreach($structure as $item) {
                        $pdf->CheckPageBreak(15);
                        if($item['type'] === 'HEADING') {
                            $pdf->SetFont('Arial', 'B', 12);
                            $pdf->SetTextColor(30, 41, 59);
                            $pdf->Cell(0, 8, strtoupper(utf8_decode($item['text'])), 0, 1, 'C');
                            $pdf->Ln(2);
                        } elseif($item['type'] === 'SUB_HEADING') {
                            $pdf->SetFont('Arial', 'B', 10);
                            $pdf->SetTextColor(79, 70, 229);
                            $pdf->Cell(0, 6, strtoupper(utf8_decode($item['text'])), 0, 1, 'L');
                            $pdf->Ln(1);
                        } else {
                            $pdf->SetX(15);
                            $this->renderPDFItem($pdf, $item, 180);
                        }
                    }
                } elseif ($subtype === 'FLOW-CHART' || $subtype === 'FLOWCHART') {
                    foreach ($structure as $item) {
                        if ($item['type'] === 'HEADING') {
                            $pdf->SetFont('Arial', 'B', 12);
                            $pdf->Cell(0, 10, strtoupper(utf8_decode($item['text'])), 0, 1, 'C');
                            $pdf->Ln(3);
                            continue;
                        }

                        $text = ($item['type'] === 'QUESTION') 
                                ? ($item['prefix'] ?? '') . " .................. " . ($item['suffix'] ?? '')
                                : $item['text'];
                        
                        $line_height = 6;
                        $nb_lines = $pdf->NbLines(120, utf8_decode($text)); 
                        $box_height = ($nb_lines * $line_height) + 6; 

                        $pdf->CheckPageBreak($box_height + 10); 
                        $y_start = $pdf->GetY();

                        if ($item['type'] === 'QUESTION') {
                            $pdf->SetDrawColor(79, 70, 229); 
                            $pdf->RoundedRect(35, $y_start, 140, $box_height, 2, 'D');
                            
                            $pdf->SetFillColor(79, 70, 229);
                            $pdf->SetTextColor(255);
                            $pdf->SetXY(35, $y_start);
                            $pdf->Cell(8, $box_height, $item['q_id'], 0, 0, 'C', true);
                            
                            $pdf->SetTextColor(30, 41, 59);
                            $pdf->SetXY(45, $y_start + 3); 
                            $pdf->MultiCell(120, $line_height, utf8_decode($text), 0, 'C');
                            
                        } elseif ($item['type'] === 'STATIC_POINT') {
                            $pdf->SetFillColor(241, 245, 249); 
                            $pdf->SetDrawColor(203, 213, 225);
                            $pdf->RoundedRect(35, $y_start, 140, $box_height, 2, 'DF');

                            $pdf->SetTextColor(71, 85, 105);
                            $pdf->SetXY(45, $y_start + 3); 
                            $pdf->MultiCell(120, $line_height, utf8_decode($text), 0, 'C');
                        }
                        
                        $pdf->SetY($y_start + $box_height);
                        
                        if ($item !== end($structure)) {
                            $pdf->DrawFlowArrow(105, $pdf->GetY());
                            $pdf->Ln(6);
                        } else {
                            $pdf->Ln(5);
                        }
                    }
                } elseif ($subtype === 'TABLE' || $subtype === 'table') {
                    $headers = $layout_data['table_headers'] ?? ['Category', 'Details', 'Features'];
                    $num_cols = count($headers);
                    $col_width = 190 / $num_cols;

                    $groups = [];
                    $current_group_name = "";
                    foreach ($structure as $item) {
                        if ($item['type'] === 'SUB_HEADING') {
                            $current_group_name = $item['text'];
                            foreach ($headers as $h) { $groups[$current_group_name][$h] = []; }
                        } elseif (in_array($item['type'], ['QUESTION', 'STATIC_POINT'])) {
                            $header_key = $item['belongs_header'] ?? '';
                            if ($current_group_name !== "" && isset($groups[$current_group_name][$header_key])) {
                                $groups[$current_group_name][$header_key][] = $item;
                            }
                        }
                    }

                    $pdf->SetFillColor(15, 23, 42); 
                    $pdf->SetTextColor(255);
                    $pdf->SetDrawColor(51, 65, 85); 
                    $pdf->SetFont('Arial', 'B', 8);
                    
                    foreach ($headers as $h) {
                        $pdf->Cell($col_width, 12, strtoupper(utf8_decode($h)), 1, 0, 'C', true);
                    }
                    $pdf->Ln();

                    $pdf->SetTextColor(30, 41, 59);
                    $pdf->SetDrawColor(226, 232, 240); 

                    foreach ($groups as $row_title => $columns) {
                        $max_h = 12; 
                        foreach ($headers as $h_name) {
                            $text_block = "";
                            foreach ($columns[$h_name] as $item) {
                                if ($item['type'] === 'QUESTION') {
                                    $text_block .= ($item['prefix'] ?? '') . " ....... " . ($item['suffix'] ?? '') . "\n";
                                } else {
                                    $text_block .= $item['text'] . "\n";
                                }
                            }
                            $cell_h = ($pdf->NbLines($col_width - 6, utf8_decode($text_block)) * 5) + 10;
                            if ($cell_h > $max_h) $max_h = $cell_h;
                        }

                        $pdf->CheckPageBreak($max_h);
                        $x_start = $pdf->GetX();
                        $y_start = $pdf->GetY();

                        foreach ($headers as $i => $h_name) {
                            $pdf->SetXY($x_start + ($i * $col_width), $y_start);
                            $pdf->Rect($pdf->GetX(), $pdf->GetY(), $col_width, $max_h);
                            $pdf->SetXY($x_start + ($i * $col_width) + 3, $y_start + 4);
                            
                            if ($i === 0) {
                                $pdf->SetFont('Arial', 'B', 9);
                                $pdf->SetTextColor(79, 70, 229); 
                                $clean_title = str_replace(['The ', 'Material: '], '', $row_title);
                                $pdf->MultiCell($col_width - 6, 5, utf8_decode($clean_title), 0, 'L');
                                $pdf->Ln(1);
                            }

                            foreach ($columns[$h_name] as $item) {
                                $pdf->SetX($x_start + ($i * $col_width) + 3);
                                if ($item['type'] === 'QUESTION') {
                                    $pdf->SetFont('Arial', 'B', 7);
                                    $pdf->SetFillColor(79, 70, 229);
                                    $pdf->SetTextColor(255);
                                    $badge_x = $pdf->GetX();
                                    $pdf->Cell(5, 5, $item['q_id'], 0, 0, 'C', true);
                                    
                                    $pdf->SetTextColor(30, 41, 59);
                                    $pdf->SetX($badge_x + 6);
                                    $pdf->SetFont('Arial', 'B', 8);
                                    $q_str = ($item['prefix'] ?? '') . " ....... " . ($item['suffix'] ?? '');
                                    $pdf->MultiCell($col_width - 12, 5, utf8_decode($q_str), 0, 'L');
                                } else {
                                    $pdf->SetFont('Arial', '', 8);
                                    $pdf->SetTextColor(100, 116, 139);
                                    $pdf->MultiCell($col_width - 6, 4, utf8_decode("- " . $item['text']), 0, 'L');
                                }
                                $pdf->Ln(1);
                            }
                        }
                        $pdf->SetY($y_start + $max_h); 
                    }
                }
            } 
            // 2. MULTIPLE CHOICE & OTHERS
            else {
                if ($subtype === 'STANDARD' || $subtype === 'MCQ') {
                    $pdf->SetFont('Arial', 'BI', 10); 
                    $pdf->SetTextColor(71, 85, 105); 
                    $pdf->MultiCell(0, 6, utf8_decode("Questions " . $b_questions[0]['id'] . " - " . end($b_questions)['id']), 0, 'L');
                    $pdf->SetFont('Arial', 'I', 10);
                    $pdf->MultiCell(0, 6, utf8_decode("Choose the correct letter, A, B or C."), 0, 'L');
                    $pdf->Ln(4);

                    foreach ($b_questions as $q) {
                        $pdf->CheckPageBreak(40);
                        $pdf->SetFillColor(79, 70, 229); 
                        $pdf->SetTextColor(255);
                        $pdf->SetFont('Arial', 'B', 10);
                        $pdf->Cell(8, 6, $q['id'], 0, 0, 'C', true);
                        
                        $pdf->SetTextColor(30, 41, 59);
                        $pdf->SetX(20);
                        $pdf->SetFont('Arial', 'B', 10); 
                        $pdf->MultiCell(170, 6, utf8_decode($q['question_text']), 0, 'L');
                        $pdf->Ln(2);
                        
                        $pdf->SetFont('Arial', '', 9); 
                        foreach ($q['options'] as $key => $val) {
                            $pdf->SetX(25);
                            $pdf->SetTextColor(79, 70, 229);
                            $pdf->SetFont('Arial', 'B', 9);
                            $pdf->Cell(8, 6, $key . ".", 0, 0, 'L');
                            $pdf->SetTextColor(100, 116, 139);
                            $pdf->SetFont('Arial', '', 9);
                            $pdf->MultiCell(0, 6, utf8_decode($val), 0, 'L');
                        }
                        $pdf->Ln(5);
                    }
                } elseif ($subtype === 'MULTI-SELECT') {
                    $grouped_questions = [];
                    foreach ($b_questions as $q) {
                        $gid = $q['task_group_id'] ?? $q['id'];
                        $grouped_questions[$gid][] = $q;
                    }

                    foreach ($grouped_questions as $gid => $group) {
                        $pdf->CheckPageBreak(60); 
                        
                        $first_q = $group[0]; 
                        $num_expected = count($group); 
                        $id_start = $group[0]['id'];
                        $id_end = end($group)['id'];
                        $id_label = ($num_expected > 1) ? "$id_start-$id_end" : "$id_start";

                        $pdf->SetFillColor(79, 70, 229); 
                        $pdf->SetTextColor(255);
                        $pdf->SetFont('Arial', 'B', 10);
                        
                        $box_width = (strlen($id_label) > 2) ? 12 : 8;
                        $pdf->Cell($box_width, 6, $id_label, 0, 0, 'C', true);
                        
                        $pdf->SetTextColor(30, 41, 59);
                        $pdf->SetX(25); 
                        $pdf->SetFont('Arial', 'B', 10);
                        $pdf->MultiCell(165, 6, utf8_decode($first_q['question_text']), 0, 'L');
                        
                        $pdf->SetX(25);
                        $pdf->SetFont('Arial', 'BI', 8);
                        $pdf->SetTextColor(79, 70, 229);
                        $label_text = ($num_expected == 2) ? "Select TWO options" : "Select THREE options";
                        $pdf->Cell(0, 5, $label_text, 0, 1, 'L');
                        $pdf->Ln(2);

                        $pdf->SetFont('Arial', '', 9);
                        $pdf->SetTextColor(100, 116, 139);
                        foreach ($first_q['options'] as $key => $val) {
                            $pdf->SetX(30);
                            $pdf->Cell(8, 6, "[  ]", 0, 0, 'C');
                            $pdf->SetFont('Arial', 'B', 9); 
                            $pdf->Cell(8, 6, $key . ".", 0, 0, 'L');
                            $pdf->SetFont('Arial', '', 9); 
                            $pdf->Cell(0, 6, utf8_decode($val), 0, 1, 'L');
                        }
                        $pdf->Ln(6);
                    }
                } elseif ($subtype === 'MATCHING') {
                    foreach ($b_questions as $q_item) {
                        $pdf->CheckPageBreak(60);
                        
                        $startX = 13;
                        $contentWidth = 185;
                        $padding = 4; 
                        $instruction = utf8_decode($q_item['question_text']);

                        $pdf->SetFont('Arial', 'B', 10);
                        $yBefore = $pdf->GetY();
                        $pdf->SetX($startX + $padding); 
                        $pdf->MultiCell($contentWidth - ($padding * 2), 5, iconv('UTF-8', 'windows-1252//TRANSLIT', $instruction), 0, 'L', false);
                        $yAfter = $pdf->GetY();

                        $textHeight = $yAfter - $yBefore;
                        $topHeaderSpace = 10; 
                        $boxHeight = $textHeight + $topHeaderSpace + 4;

                        $pdf->SetY($yBefore); 
                        $pdf->SetFillColor(249, 250, 251); 
                        $pdf->Rect($startX, $yBefore, $contentWidth, $boxHeight, 'F');
                        $pdf->SetFillColor(51, 65, 85); 
                        $pdf->Rect($startX, $yBefore, 1, $boxHeight, 'F');

                        $pdf->SetY($yBefore + 2.5);
                        $pdf->SetX($startX + $padding);
                        $pdf->SetFont('Arial', 'B', 7);
                        $pdf->SetTextColor(51, 65, 85);
                        $pdf->SetDrawColor(203, 213, 225);
                        $pdf->Cell(18, 4, 'QUESTION', 1, 0, 'C', false); 

                        $pdf->SetY($yBefore + $topHeaderSpace);
                        $pdf->SetX($startX + $padding);
                        $pdf->SetFont('Arial', 'B', 10);
                        $pdf->SetTextColor(30, 41, 59);
                        $pdf->MultiCell($contentWidth - ($padding * 2), 5, $instruction, 0, 'L');

                        $pdf->SetY($yBefore + $boxHeight + 5);

                        $pdf->SetFont('Arial', 'B', 9);
                        $startX = 15;
                        $containerWidth = 180;
                        $leftColX = 20;
                        $rightColX = 105;
                        $colWidth = 75; 
                        $lineHeight = 5; 
                        
                        $options = $q_item['options'];
                        $optionKeys = array_keys($options);
                        $yBoxStart = $pdf->GetY();

                        $totalBoxHeight = 4; 
                        $rowHeights = [];
                        for ($i = 0; $i < count($optionKeys); $i += 2) {
                            $keyL = $optionKeys[$i];
                            $valL = utf8_decode($options[$keyL]);
                            $linesL = $pdf->WordWrap($valL, $colWidth); 
                            $hL = $linesL * $lineHeight + 2;

                            $hR = 0;
                            if (isset($optionKeys[$i+1])) {
                                $keyR = $optionKeys[$i+1];
                                $valR = utf8_decode($options[$keyR]);
                                $linesR = $pdf->WordWrap($valR, $colWidth);
                                $hR = $linesR * $lineHeight + 2;
                            }
                            
                            $maxH = max($hL, $hR, 7); 
                            $rowHeights[] = $maxH;
                            $totalBoxHeight += $maxH;
                        }

                        $pdf->SetFillColor(255, 255, 255);
                        $pdf->RoundedRect($startX, $yBoxStart, $containerWidth, $totalBoxHeight, 2, 'D');

                        $pdf->SetY($yBoxStart + 2);
                        $rowIndex = 0;
                        for ($i = 0; $i < count($optionKeys); $i += 2) {
                            $currY = $pdf->GetY();
                            $rowH = $rowHeights[$rowIndex];

                            $keyL = $optionKeys[$i];
                            $pdf->SetX($leftColX);
                            $pdf->SetTextColor(79, 70, 229); 
                            $pdf->Cell(6, $lineHeight, $keyL, 0, 0);
                            $pdf->SetTextColor(30, 41, 59); 
                            $pdf->MultiCell($colWidth, $lineHeight, utf8_decode($options[$keyL]), 0, 'L');

                            if (isset($optionKeys[$i+1])) {
                                $keyR = $optionKeys[$i+1];
                                $pdf->SetY($currY); 
                                $pdf->SetX($rightColX);
                                $pdf->SetTextColor(79, 70, 229);
                                $pdf->Cell(6, $lineHeight, $keyR, 0, 0);
                                $pdf->SetTextColor(30, 41, 59);
                                $pdf->MultiCell($colWidth, $lineHeight, utf8_decode($options[$keyR]), 0, 'L');
                            }

                            $pdf->SetY($currY + $rowH); 
                            $rowIndex++;
                        }
                        
                        $pdf->SetY($yBoxStart + $totalBoxHeight + 6);

                        foreach ($q_item['items'] as $item) {
                            $pdf->CheckPageBreak(20);
                            $itemStartX = 15;
                            $currentY = $pdf->GetY();
                            
                            $pdf->SetFont('Arial', '', 10);
                            $textWidth = 130;
                            $hBefore = $pdf->GetY();
                            $pdf->SetX($itemStartX + 32); 
                            $pdf->MultiCell($textWidth, 8, utf8_decode($item['text']), 0, 'L', false);
                            $hAfter = $pdf->GetY();
                            $cellHeight = max(8, $hAfter - $hBefore);

                            $pdf->SetY($currentY);
                            $pdf->SetX($itemStartX);
                            $pdf->SetFillColor(79, 70, 229);
                            $pdf->SetTextColor(255);
                            $pdf->SetFont('Arial', 'B', 10);
                            $pdf->Cell(10, $cellHeight, $item['id'], 0, 0, 'C', true);
                            $pdf->SetTextColor(30, 41, 59);
                            $pdf->Cell(22, $cellHeight, " [        ] ", 0, 0, 'C');
                            $pdf->SetY($currentY + $cellHeight + 2);
                        }
                        $pdf->Ln(5);
                    }
                } elseif (in_array($subtype, ['OPINION', 'CLASSIFICATION', 'PROCEDURAL', 'FACT_MATCHING', 'MATCHING'])) {
                    $question_blocks = [];
                    foreach ($b_questions as $q) {
                        $groupId = $q['task_group_id'] ?? $q['instruction']; 
                        $question_blocks[$groupId][] = $q;
                    }

                    foreach ($question_blocks as $groupId => $block_items) {
                        $pdf->CheckPageBreak(60);
                        
                        $first_entry = $block_items[0];
                        $options = $first_entry['matching_options'] ?? ($first_entry['options'] ?? []);
                        $instruction = $first_entry['instruction'] ?? ($first_entry['question_text'] ?? "Match the following items.");
                        
                        $normalized_sub_questions = [];
                        $block_answers = [];

                        foreach ($block_items as $q) {
                            if (isset($q['items']) && is_array($q['items'])) {
                                foreach ($q['items'] as $subItem) { $normalized_sub_questions[] = $subItem; }
                                if (isset($q['answer']) && is_array($q['answer'])) {
                                    $block_answers = array_merge($block_answers, array_values($q['answer']));
                                }
                            } else {
                                $normalized_sub_questions[] = ['id' => $q['id'], 'text' => $q['text'] ?? $q['question_text'] ?? ''];
                                if (isset($q['answer'])) {
                                    $block_answers[] = is_array($q['answer']) ? reset($q['answer']) : $q['answer'];
                                }
                            }
                        }

                        $has_duplicates = false;
                        $answer_counts = array_count_values(array_filter($block_answers));
                        foreach ($answer_counts as $count) { if ($count > 1) { $has_duplicates = true; break; } }

                        $startX = 13; $contentWidth = 185; $padding = 4; 
                        $ids = array_column($normalized_sub_questions, 'id');
                        $q_range = (min($ids) == max($ids)) ? min($ids) : min($ids) . '-' . max($ids);

                        $pdf->SetFont('Arial', 'B', 10);
                        $yBefore = $pdf->GetY();
                        $pdf->SetX($startX + $padding); 
                        $pdf->MultiCell($contentWidth - ($padding * 2), 5, iconv('UTF-8', 'windows-1252//TRANSLIT', $instruction), 0, 'L', false);
                        $yAfter = $pdf->GetY();
                        $boxHeight = ($yAfter - $yBefore) + 14 + ($has_duplicates ? 6 : 0); 

                        $pdf->SetY($yBefore); 
                        $pdf->SetFillColor(249, 250, 251); 
                        $pdf->Rect($startX, $yBefore, $contentWidth, $boxHeight, 'F');
                        $pdf->SetFillColor(51, 65, 85); 
                        $pdf->Rect($startX, $yBefore, 1, $boxHeight, 'F');

                        $pdf->SetY($yBefore + 2.5); $pdf->SetX($startX + $padding);
                        $pdf->SetFont('Arial', 'B', 7); $pdf->SetTextColor(51, 65, 85); $pdf->SetDrawColor(203, 213, 225);
                        $pdf->Cell(18, 4, 'INSTRUCTION', 1, 0, 'C', false); 
                        $pdf->SetX($startX + 24); $pdf->SetFont('Arial', 'I', 7);
                        $pdf->Cell(0, 4, 'Questions ' . $q_range, 0, 0, 'L');

                        $pdf->SetY($yBefore + 10); $pdf->SetX($startX + $padding);
                        $pdf->SetFont('Arial', 'B', 10); $pdf->SetTextColor(30, 41, 59);
                        $pdf->MultiCell($contentWidth - ($padding * 2), 5, iconv('UTF-8', 'windows-1252//TRANSLIT', $instruction), 0, 'L');

                        if ($has_duplicates) {
                            $pdf->SetX($startX + $padding); $pdf->SetFont('Arial', 'BI', 8); $pdf->SetTextColor(185, 28, 28);
                            $pdf->Cell(0, 5, "NB: You may use any letter more than once.", 0, 1, 'L');
                        }

                        $pdf->SetY($yBefore + $boxHeight + 5);

                        $boxStartY = $pdf->GetY();
                        $rowHeight = 7; $totalBoxHeight = (count($options) * $rowHeight) + 6;
                        $pdf->SetFillColor(248, 250, 252); $pdf->SetDrawColor(226, 232, 240); 
                        $pdf->RoundedRect(10, $boxStartY, 190, $totalBoxHeight, 4, 'DF');
                        $pdf->SetY($boxStartY + 3);

                        foreach ($options as $key => $opt) {
                            $pdf->SetX(15);
                            if (strpos($opt, '. ') !== false) {
                                $parts = explode('. ', $opt, 2);
                                $letter = $parts[0]; $label = $parts[1];
                            } else {
                                $letter = $key; $label = $opt;
                            }
                            $pdf->SetFont('Arial', 'B', 10); $pdf->SetTextColor(79, 70, 229);
                            $pdf->Cell(6, $rowHeight, $letter . ".", 0, 0, 'L');
                            $pdf->SetFont('Arial', '', 10); $pdf->SetTextColor(51, 65, 85);
                            $pdf->Cell(0, $rowHeight, iconv('UTF-8', 'windows-1252//TRANSLIT', $label), 0, 1, 'L');
                        }

                        $pdf->Ln(8);

                        foreach ($normalized_sub_questions as $q) {
                            $text = iconv('UTF-8', 'windows-1252//TRANSLIT', $q['text'] ?? '');
                            $h = max(6, $pdf->NbLines(155, $text) * 6);
                            $pdf->CheckPageBreak($h + 5);
                            $currY = $pdf->GetY();
                            $pdf->SetFillColor(79, 70, 229); $pdf->SetTextColor(255); $pdf->SetFont('Arial', 'B', 10);
                            $pdf->Cell(8, 6, $q['id'], 0, 0, 'C', true);
                            $pdf->SetTextColor(30, 41, 59); $pdf->Cell(20, 6, " [      ] ", 0, 0, 'C');
                            $pdf->SetFont('Arial', '', 10); $pdf->MultiCell(155, 6, $text, 0, 'L');
                            $pdf->SetY($currY + $h + 3);
                        }
                        $pdf->Ln(5); 
                    }
                }
            }
        }

        // --- ANSWER KEY ---
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetTextColor(30, 41, 59); 
        $pdf->Cell(0, 20, "Answer Key", 0, 1, 'L');

        $global_q_count = 1; 

        foreach ($questions as $q) {
            $subtype = strtoupper($q['subtype'] ?? $q['completion_type'] ?? '');
            
            if (in_array($subtype, ['MATCHING', 'PROCEDURAL', 'FACT_MATCHING', 'CLASSIFICATION', 'OPINION']) && isset($q['items']) && is_array($q['items'])) {
                foreach ($q['items'] as $item) {
                    $item_id = $item['id'];
                    $ans_letter = '';
                    if (isset($q['answer']) && is_array($q['answer'])) {
                        $ans_letter = $q['answer'][(string)$item_id] ?? $q['answer'][$item_id] ?? '';
                    } elseif (isset($q['answer'])) {
                        $ans_letter = $q['answer'];
                    }
                    $this->renderAnswerRow($pdf, $global_q_count, $ans_letter);
                    $global_q_count++;
                }
            } else {
                $raw_answer = $q['answer'] ?? '';
                if (is_array($raw_answer)) {
                    $final_text = reset($raw_answer); 
                } else {
                    $final_text = (string)$raw_answer;
                }
                $this->renderAnswerRow($pdf, $global_q_count, $final_text);
                $global_q_count++;
            }
        }

        // 7. RETURN PDF RESPONSE
       if (ob_get_length()) ob_end_clean();

    // 1. Generate the PDF content as a string
    $pdfContent = $pdf->Output('S'); 

    // 2. Return the download response
    return response($pdfContent)
        ->header('Content-Type', 'application/pdf')
        ->header('Content-Disposition', 'attachment; filename="Listening_Test_' . $id . '.pdf"')
        ->header('Pragma', 'public')
        ->header('Cache-Control', 'private, max-age=0, must-revalidate');
}

    // --- HELPER METHODS ---

    private function renderPDFItem($pdf, $item, $width) {
        if ($item['type'] === 'QUESTION') {
            $pdf->SetFillColor(79, 70, 229);
            $pdf->SetTextColor(255);
            $pdf->SetFont('Arial', 'B', 8);
            $current_x = $pdf->GetX();
            $pdf->Cell(6, 6, $item['q_id'], 0, 0, 'C', true);
            $pdf->SetTextColor(30, 41, 59);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetX($current_x + 8);
            $text = ($item['prefix'] ?? '') . " ........................ " . ($item['suffix'] ?? '');
            $pdf->MultiCell($width - 10, 5, utf8_decode($text), 0, 'L');
            $pdf->Ln(2);
        } else {
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetTextColor(71, 85, 105);
            $pdf->MultiCell($width, 4, utf8_decode("- " . $item['text']), 0, 'L');
            $pdf->Ln(2);
        }
    }

    private function renderAnswerRow($pdf, $num, $text) {
        $pdf->SetTextColor(79, 70, 229); 
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(15, 10, $num, 1, 0, 'C');
        
        $pdf->SetTextColor(30, 41, 59); 
        $pdf->SetFont('Arial', 'B', 11);
        
        $ln = ($num % 2 == 0) ? 1 : 0;
        $pdf->Cell(80, 10, "  " . utf8_decode($text), 1, $ln, 'L');
    }



 public function downloadReading($id)
    {
        // 1. SECURITY & DOWNLOAD BALANCE CHECK
        $user = Auth::user();
        if (!$user) {
            abort(401, 'Access denied. Please log in.');
        }

        $maxDownloads = ($user->plan_type === 'pro') ? 100 : 5; // Adjust free limit as needed
        $remaining = $maxDownloads - $user->download_attempts;

        if ($remaining <= 0) {
            abort(403, 'You have reached your download limit for this month.');
        }

        // 2. FETCH DATA & OWNERSHIP CHECK (From reading_tests table)
        $test = DB::table('reading_tests')
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$test) {
            abort(404, 'Test not found or access denied.');
        }

        // 3. INCREMENT DOWNLOAD ATTEMPTS
        DB::table('users')->where('id', $user->id)->increment('download_attempts');

        // 4. GENERATE PDF
        $pdf = new READING_PDF();
        $pdf->SetMargins(20, 20, 20);
        $pdf->AddPage();

        // Title
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->MultiCell(0, 10, strtoupper(utf8_decode($test->topic)), 0, 'C');
        $pdf->Ln(5);

        // Reading Passage (Clean up logic)
        $clean_passage = preg_replace('/```json.*?```/s', '', $test->passage);
        $clean_passage = preg_replace('/^---$/m', '', $clean_passage);
        $clean_passage = preg_replace('/\*\*Trap Output JSON:\*\*/', '', $clean_passage);
        $clean_passage = preg_replace('/\[\]\s+/', '', $clean_passage);
        $clean_passage = trim($clean_passage);
        $clean_passage = preg_replace("/\n\s*\n/", "\n\n", $clean_passage);

        $pdf->SetFont('Arial', '', 11);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->MultiCell(0, 7, utf8_decode($clean_passage));
        $pdf->Ln(15);

        // Questions Section
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, "QUESTIONS", 'B', 1, 'L');
        $pdf->Ln(5);

        $questions = json_decode($test->questions, true) ?? [];
        $global_q_num = 1;
        $last_subtype = ''; 

        foreach ($questions as $q) {
            $subtype = $q['subtype'] ?? 'STANDARD';
            $metadata = $q['metadata'] ?? [];

            // Grouped Instruction Box
            if ($subtype !== $last_subtype) {
                $pdf->Ln(5);
                switch ($subtype) {
                    case 'MATCHING-INFO':
                        $pdf->Instruction($q['prefix'] ?? "Which paragraph contains the following information? NB: You may use any letter more than once.");
                        break;
                    case 'MATCHING-FEATURES':
                        $pdf->Instruction($q['prefix'] ?? "Match each statement with the correct item/person below.");
                        break;
                    case 'MATCHING-HEADINGS':
                        $pdf->Instruction($q['prefix'] ?? "Choose the correct heading for each paragraph from the list of headings below.");
                        break;
                    case 'SUMMARY-BANK':
                        $pdf->Instruction("Complete the summary using the list of words below.");
                        break;
                    case 'TFNG':
                        $pdf->Instruction($q['prefix'] ?? "Do the following statements agree with the information given in the Reading Passage? Write TRUE, FALSE, or NOT GIVEN.");
                        break;
                    case 'YNNG':
                        $pdf->Instruction($q['prefix'] ?? "Do the following statements agree with the claims of the writer? Write YES, NO, or NOT GIVEN.");
                        break;
                    case 'STANDARD':
                        $pdf->Instruction("Complete the sentences below. Choose NO MORE THAN TWO WORDS from the passage for each answer.");
                        break;
                }

                // Options Box
                $options = $metadata['options_box'] ?? $q['options_box'] ?? [];
                if (in_array($subtype, ['TFNG', 'YNNG'])) {
                    $pdf->SetFillColor(245, 245, 245);
                    $pdf->SetFont('Arial', 'B', 10);
                    $pdf->Cell(0, 8, "    Write your answers as:", 1, 1, 'L', true);
                    $pdf->SetFont('Arial', '', 10);
                    
                    if ($subtype === 'TFNG') {
                        $pdf->Cell(0, 6, "    TRUE           if the statement agrees with the information", 'LR', 1, 'L', true);
                        $pdf->Cell(0, 6, "    FALSE          if the statement contradicts the information", 'LR', 1, 'L', true);
                        $pdf->Cell(0, 6, "    NOT GIVEN      if there is no information on this", 'LR', 1, 'L', true);
                    } else {
                        $pdf->Cell(0, 6, "    YES            if the statement agrees with the claims of the writer", 'LR', 1, 'L', true);
                        $pdf->Cell(0, 6, "    NO             if the statement contradicts the claims of the writer", 'LR', 1, 'L', true);
                        $pdf->Cell(0, 6, "    NOT GIVEN      if it is impossible to say what the writer thinks", 'LR', 1, 'L', true);
                    }
                    $pdf->Cell(0, 2, "", 'T', 1); 
                    $pdf->Ln(5);
                    
                } elseif (!empty($options) && in_array($subtype, ['MATCHING-HEADINGS', 'MATCHING-FEATURES', 'MATCHING-INFO', 'SUMMARY-BANK'])) {
                    $pdf->SetFillColor(245, 245, 245);
                    $pdf->SetFont('Arial', 'B', 10);
                    
                    $box_title = "    Available Options:";
                    if ($subtype === 'MATCHING-HEADINGS') $box_title = "    List of Headings:";
                    if ($subtype === 'SUMMARY-BANK') $box_title = "    Word Bank:";
                    if ($subtype === 'MATCHING-INFO') $box_title = "    Paragraph Options:";

                    if ($subtype === 'SUMMARY-BANK' || $subtype === 'MATCHING-INFO') {
                        $pdf->Cell(0, 8, $box_title, 'TRL', 1, 'L', true);
                        $pdf->SetFont('Arial', '', 9); 
                        $page_width = 170; 
                        $cols = 2; 
                        $col_width = $page_width / $cols;
                        $current_col = 0;

                        foreach ($options as $opt) {
                            $border = ($current_col == $cols - 1) ? 'LR' : 'L';
                            $pdf->Cell($col_width, 7, "  " . utf8_decode($opt), $border, 0, 'L', true);
                            $current_col++;
                            if ($current_col == $cols) {
                                $pdf->Ln();
                                $current_col = 0;
                            }
                        }
                        if ($current_col > 0) {
                            $pdf->Cell($col_width, 7, "", 'R', 1, 'L', true);
                        }
                        $pdf->Cell(0, 1, "", 'T', 1); 
                    } else {
                        $pdf->Cell(0, 8, $box_title, 1, 1, 'L', true);
                        $pdf->SetFont('Arial', '', 10);
                        foreach ($options as $opt) {
                            $pdf->Cell(0, 6, "    " . utf8_decode($opt), 'LR', 1, 'L', true);
                        }
                        $pdf->Cell(0, 2, "", 'T', 1); 
                    }
                    $pdf->Ln(5);
                }
            }
            $last_subtype = $subtype;

            // Renderer Dispatcher
            switch ($subtype) {
                case 'MATCHING-HEADINGS':
                    if (isset($q['answer']) && is_array($q['answer'])) {
                        foreach ($q['answer'] as $para => $ans) {
                            $pdf->SetFont('Arial', 'B', 11);
                            $pdf->Cell(40, 8, "  " . $global_q_num . ". " . $para, 0, 0);
                            $pdf->Cell(0, 8, "..................", 0, 1);
                            $global_q_num++;
                        }
                    }
                    break;

                case 'SUMMARY-BANK':
                    $pdf->SetFont('Arial', '', 11);
                    $pdf->MultiCell(0, 7, utf8_decode($q['summary_text'] ?? ''));
                    $pdf->Ln(4);
                    if (isset($q['questions_options'])) {
                        foreach ($q['questions_options'] as $subQ) {
                            $pdf->SetFont('Arial', 'B', 11);
                            $pdf->Cell(40, 8, "  " . $global_q_num . ". Gap [" . $subQ['gap_number'] . "]", 0, 0);
                            $pdf->Cell(0, 8, "..................", 0, 1);
                            $global_q_num++;
                        }
                    }
                    break;

                case 'MATCHING-INFO':
                case 'MATCHING-FEATURES':
                case 'TFNG':
                case 'YNNG':
                    $statement = $q['question_statement'] ?? $metadata['question_statement'] ?? "Statement missing";
                    $pdf->QuestionRow($global_q_num, $statement);
                    $global_q_num++;
                    break;

                case 'MCQ-SINGLE':
                case 'MCQ-MULTI':
                case 'MCQ-GLOBAL':
                    $q_text = $q['question_text'] ?? $q['prefix'] ?? "Question missing";
                    $pdf->QuestionRow($global_q_num, $q_text);
                    $mcq_options = $metadata['options_box'] ?? [];
                    foreach ($mcq_options as $opt) {
                        $pdf->Cell(10); 
                        $pdf->SetFont('Arial', '', 10);
                        $clean_opt = preg_replace('/\[.*?\]\s*-\s*/', '', $opt);
                        $pdf->MultiCell(0, 6, utf8_decode($clean_opt));
                    }
                    $global_q_num++;
                    break;

                case 'STANDARD':
                default:
                    $full_text = ($q['prefix'] ?? '') . " .................... " . ($q['suffix'] ?? '');
                    $pdf->QuestionRow($global_q_num, $full_text);
                    $global_q_num++;
                    break;
            }
            $pdf->Ln(2);
        }

        // Answer Key Page
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 15, "ANSWER KEY", 0, 1, 'C');
        $pdf->Ln(5);

        $answers_raw = json_decode($test->answers, true) ?? [];
        $display_num = 1;

        $pdf->SetFont('Arial', '', 12);
        if (is_array($answers_raw)) {
            foreach ($answers_raw as $ans) {
                if (is_array($ans)) {
                    foreach ($ans as $para => $val) {
                        $pdf->SetFont('Arial', 'B', 12);
                        $pdf->Write(8, "$display_num. ");
                        $pdf->SetFont('Arial', '', 12);
                        $pdf->Write(8, "$para: $val\n");
                        $display_num++;
                    }
                } else {
                    $pdf->SetFont('Arial', 'B', 12);
                    $pdf->Write(8, "$display_num. ");
                    $pdf->SetFont('Arial', '', 12);
                    $pdf->Write(8, "$ans\n");
                    $display_num++;
                }
            }
        }

        // 5. SECURE OUTPUT (Laravel Style)
        if (ob_get_length()) ob_end_clean(); // Prevent file corruption

        $pdfContent = $pdf->Output('S'); 
        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $test->topic) . ".pdf";

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Pragma', 'public')
            ->header('Cache-Control', 'private, max-age=0, must-revalidate');
    }



    
}



// ---------------------------------------------------------
// 1. FPDF CLASS EXTENSION FOR READING
// Put this outside/above your main controller class
// ---------------------------------------------------------
class READING_PDF extends FPDF {
    function Header() {
        if ($this->PageNo() == 1) {
            $this->SetFont('Arial', 'B', 8);
            $this->SetTextColor(128, 128, 128);
            $this->Cell(0, 10, 'IELTS Lab - Academic Reading Practice', 0, 0, 'R');
            $this->Ln(15);
        }
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' - IELTS Lab Professional', 0, 0, 'C');
    }

    // Instruction Box
    function Instruction($txt) {
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 10, "Questions:", 0, 1);
        $this->SetFont('Arial', 'I', 10);
        $this->MultiCell(0, 6, utf8_decode($txt));
        $this->Ln(4);
    }

    // Standard Question Row
    function QuestionRow($num, $txt) {
        $this->SetFont('Arial', 'B', 11);
        $this->Write(6, $num . ". ");
        $this->SetFont('Arial', '', 11);
        $this->MultiCell(0, 6, utf8_decode($txt));
        $this->Ln(2);
    }
}

// ---------------------------------------------------------
// 2. MAIN CONTROLLER CLASS
// ---------------------------------------------------------

    // ... your downloadListening method ...

   