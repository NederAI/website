<?php

namespace App\Tools;

class SimpleMarkdownHtml
{
    // Public entry point: Converts Markdown text to HTML.
    public function parse(string $markdown): string
    {
        // Split sections using horizontal rules (a line with only ---).
        $sections = preg_split('/^\s*---\s*$/m', $markdown);
        
        $html = "";
        foreach ($sections as $section) {
            $html .= "<section>\n" . $this->parseBlocks($section) . "\n</section>\n";
        }
        return $html;
    }

    // Processes block-level elements.
    // This implementation now handles headers, code blocks, paragraphs,
    // unordered lists, ordered lists, and supports multi-line list items.
    // It also treats list items separated by multiple blank lines as part of a single list.
    protected function parseBlocks(string $text): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $text);
        $html = "";
        $inCodeBlock = false;
        $paragraphBuffer = [];

        // We keep track of whether a list is active:
        //    $currentListType: 'ul' for unordered, 'ol' for ordered, or null if none.
        //    $listBuffer: an array to accumulate list-item texts.
        $currentListType = null;
        $listBuffer = [];

        // Helper: flush any pending paragraph.
        $flushParagraph = function() use (&$paragraphBuffer, &$html) {
            if (!empty($paragraphBuffer)) {
                $paragraphText = implode(' ', $paragraphBuffer);
                $html .= "<p>" . $this->parseInline(trim($paragraphText)) . "</p>\n";
                $paragraphBuffer = [];
            }
        };

        // Helper: flush any pending list.
        $flushList = function() use (&$listBuffer, &$html, &$currentListType) {
            if (!empty($listBuffer) && $currentListType !== null) {
                $html .= "<" . $currentListType . ">\n";
                foreach ($listBuffer as $item) {
                    $html .= "<li>" . $this->parseInline(trim($item)) . "</li>\n";
                }
                $html .= "</" . $currentListType . ">\n";
                $listBuffer = [];
                $currentListType = null;
            }
        };

        // Process each line with some look-ahead behavior.
        $skipUntil = -1;
        foreach ($lines as $index => $line) {
            if ($skipUntil >= 0 && $index <= $skipUntil) {
                continue;
            }
            // Remove any trailing whitespace.
            $trimmedLine = rtrim($line);

            // Toggle code block state when encountering triple backticks.
            if (preg_match('/^```/', $trimmedLine)) {
                // Always flush any pending paragraph or list before switching modes.
                $flushParagraph();
                $flushList();
                if (!$inCodeBlock) {
                    $inCodeBlock = true;
                    $html .= "<pre><code>";
                } else {
                    $inCodeBlock = false;
                    $html .= "</code></pre>\n";
                }
                continue;
            }

            // If inside a code block, output verbatim.
            if ($inCodeBlock) {
                $html .= htmlspecialchars($line) . "\n";
                continue;
            }

            if ($this->looksLikeTableHeaderLine($trimmedLine) && isset($lines[$index + 1]) && $this->looksLikeTableDividerLine($lines[$index + 1])) {
                $flushParagraph();
                $flushList();
                list($tableHtml, $consumedIndex) = $this->buildTable($lines, $index);
                if ($tableHtml !== '') {
                    $html .= $tableHtml;
                }
                $skipUntil = $consumedIndex;
                continue;
            }

            // Check for an unordered list marker (e.g. "- item", "* item", or "+ item").
            if (preg_match('/^\s*([-*+])\s+(.+)$/', $trimmedLine, $matches)) {
                // Flush any pending paragraph.
                $flushParagraph();
                // If a list is already active but of a different type, flush it first.
                if ($currentListType !== null && $currentListType !== 'ul') {
                    $flushList();
                }
                // Start or continue an unordered list.
                if ($currentListType === null) {
                    $currentListType = 'ul';
                }
                $listBuffer[] = $matches[2];
                continue;
            }

            // Check for a numeric (ordered) list marker (e.g. "1. item" or "1) item").
            if (preg_match('/^\s*(\d+)[\.\)]\s+(.+)$/', $trimmedLine, $matches)) {
                // Flush paragraph if needed.
                $flushParagraph();
                // If a list is already active but of a different type, flush it.
                if ($currentListType !== null && $currentListType !== 'ol') {
                    $flushList();
                }
                // Start or continue an ordered list.
                if ($currentListType === null) {
                    $currentListType = 'ol';
                }
                $listBuffer[] = $matches[2];
                continue;
            }

            // Check for indented continuation: if a list is active and the line is indented,
            // treat it as a continuation of the previous list item.
            if ($currentListType !== null && preg_match('/^( {1,}|\t+)(.*)$/', $line, $matches)) {
                // Only append if there is some nonempty continuation text.
                if (strlen(trim($matches[2])) > 0) {
                    // Append with a <br /> to preserve line breaks.
                    $lastIndex = count($listBuffer) - 1;
                    $listBuffer[$lastIndex] .= "<br />" . trim($matches[2]);
                    continue;
                }
            }

            // Check for headers (lines starting with 1-6 '#' characters).
            if (preg_match('/^(#{1,6})\s+(.*)$/', $trimmedLine, $matches)) {
                // A header should flush both any pending paragraph and any active list.
                $flushParagraph();
                $flushList();
                $level = strlen($matches[1]);
                $content = $this->parseInline(trim($matches[2]));
                $html .= "<h{$level}>{$content}</h{$level}>\n";
                continue;
            }

            // Check for a horizontal rule.
            if (preg_match('/^[-*]{3,}$/', trim($line))) {
                $flushParagraph();
                $flushList();
                $html .= "<hr />\n";
                continue;
            }

            // If the line is blank...
            if (trim($line) === '') {
                // Flush paragraph buffer but intentionally leave any active list intact.
                $flushParagraph();
                continue;
            }

            // For any other non-blank line:
            // If a list is active but the line does not start with a list marker (nor is it indented),
            // then that signals the end of the list. Flush the list first.
            if ($currentListType !== null) {
                $flushList();
            }
            // Then accumulate the line as part of a paragraph.
            $paragraphBuffer[] = $line;
        }

        // Flush any remaining buffers.
        $flushParagraph();
        $flushList();

        return $html;
    }

    // Processes inline-level elements like bold, italic, and inline code.


    private function looksLikeTableHeaderLine(string $line): bool
    {
        return $this->looksLikeTableRowLine($line);
    }

    private function looksLikeTableDividerLine(string $line): bool
    {
        $line = trim($line);
        if ($line === '') {
            return false;
        }
        if (strpos($line, '|') === 0) {
            $line = substr($line, 1);
        }
        if (substr($line, -1) === '|') {
            $line = substr($line, 0, -1);
        }
        $parts = explode('|', $line);
        if (count($parts) < 1) {
            return false;
        }
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                return false;
            }
            if (!preg_match('/^:?-{3,}:?$/', $part)) {
                return false;
            }
        }
        return true;
    }

    private function looksLikeTableRowLine(string $line): bool
    {
        $line = trim($line);
        if ($line === '') {
            return false;
        }
        if (strpos($line, '|') === false) {
            return false;
        }
        return preg_match('/^\|?.*\|.*\|?$/', $line) === 1;
    }

    private function buildTable(array $lines, int $startIndex): array
    {
        $headerLine = $lines[$startIndex];
        $dividerLine = $lines[$startIndex + 1] ?? '';
        $headerCells = $this->splitTableRow($headerLine);
        $columnCount = count($headerCells);
        if ($columnCount === 0) {
            return ['', $startIndex];
        }
        $alignments = $this->parseTableAlignments($dividerLine, $columnCount);

        $bodyRows = [];
        $lastIndex = $startIndex + 1;
        $totalLines = count($lines);

        for ($rowIndex = $startIndex + 2; $rowIndex < $totalLines; $rowIndex++) {
            $candidate = $lines[$rowIndex];
            if (trim($candidate) === '') {
                break;
            }
            if (!$this->looksLikeTableRowLine($candidate)) {
                break;
            }
            $cells = $this->splitTableRow($candidate);
            if (count($cells) < $columnCount) {
                $cells = array_pad($cells, $columnCount, '');
            } elseif (count($cells) > $columnCount) {
                $cells = array_slice($cells, 0, $columnCount);
            }
            $bodyRows[] = $cells;
            $lastIndex = $rowIndex;
        }

        $tableHtml = "<table>
<thead>
<tr>";
        foreach ($headerCells as $idx => $cell) {
            $tableHtml .= $this->renderTableCell('th', $cell, $alignments[$idx] ?? null);
        }
        $tableHtml .= "</tr>
</thead>
<tbody>
";

        foreach ($bodyRows as $row) {
            $tableHtml .= "<tr>";
            foreach ($row as $idx => $cell) {
                $tableHtml .= $this->renderTableCell('td', $cell, $alignments[$idx] ?? null);
            }
            $tableHtml .= "</tr>
";
        }

        $tableHtml .= "</tbody>
</table>
";

        return [$tableHtml, $lastIndex];
    }

    private function splitTableRow(string $line): array
    {
        $line = trim($line);
        if ($line === '') {
            return [];
        }

        $placeholder = '__PIPE_ESC__';
        $line = str_replace('\|', $placeholder, $line);

        if (strpos($line, '|') === 0) {
            $line = substr($line, 1);
        }
        if (substr($line, -1) === '|') {
            $line = substr($line, 0, -1);
        }

        $parts = explode('|', $line);
        $cells = [];
        foreach ($parts as $part) {
            $part = trim(str_replace($placeholder, '|', $part));
            $cells[] = $this->parseInline($part);
        }
        return $cells;
    }

    private function parseTableAlignments(string $line, int $columns): array
    {
        $line = trim($line);
        if ($line === '') {
            return array_fill(0, $columns, null);
        }

        if (strpos($line, '|') === 0) {
            $line = substr($line, 1);
        }
        if (substr($line, -1) === '|') {
            $line = substr($line, 0, -1);
        }

        $parts = explode('|', $line);
        $alignments = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                $alignments[] = null;
                continue;
            }
            $left = $part[0] === ':';
            $right = substr($part, -1) === ':';
            if ($left && $right) {
                $alignments[] = 'center';
            } elseif ($right) {
                $alignments[] = 'right';
            } elseif ($left) {
                $alignments[] = 'left';
            } else {
                $alignments[] = null;
            }
        }

        if (count($alignments) < $columns) {
            $alignments = array_pad($alignments, $columns, null);
        }
        if (count($alignments) > $columns) {
            $alignments = array_slice($alignments, 0, $columns);
        }

        return $alignments;
    }

    private function renderTableCell(string $tag, string $content, ?string $alignment): string
    {
        $attr = '';
        if ($alignment !== null) {
            $attr = ' style="text-align:' . $alignment . ';"';
        }
        return '<' . $tag . $attr . '>' . $content . '</' . $tag . '>';
    }

    protected function parseInline(string $text): string
    {
        // Bold: **text**
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        
        // Italic: *text*
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        
        // Inline code: `code`
        $text = preg_replace('/`(.+?)`/', '<code>$1</code>', $text);
        
        // Additional inline syntaxes can be added here.
        return $text;
    }
}
