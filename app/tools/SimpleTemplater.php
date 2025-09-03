<?php

namespace App\Tools;

class SimpleTemplater {
    // The raw template text.
    protected $template = "";
    
    // Hierarchical data array including both simple variables and block data.
    protected $data = array();

    public function setTemplate($template) {
        $this->template = $template;
    }
    
    // Assign a global (or top-level) variable.
    public function assign($name, $value) {
        $this->data[$name] = $value;
    }
    
    // Assign a block row to a given block name.
    public function assignBlock($blockName, $dataRow) {
        if (!isset($this->data[$blockName]) || !is_array($this->data[$blockName])) {
            $this->data[$blockName] = array();
        }
        $this->data[$blockName][] = $dataRow;
    }
    
    // Assign the entire data set (including nested block arrays) at once.
    // If a value is an array, it might be a block (if it contains arrays) or a variable.
    public function assignAll($data) {
        // Merge with any previously assigned data.
        $this->data = array_merge($this->data, $data);
    }
    
    // Render the final content by recursively processing the template.
    public function render() {
        return $this->renderTemplate($this->template, $this->data);
    }
    
    // Recursively process a template fragment with the given data scope.
    protected function renderTemplate($template, $data) {
        // First process any block constructs.
        // Blocks are defined as {block:blockname}...{/block:blockname}
        $pattern = '/\{block:([a-zA-Z0-9_]+)\}(.*?)\{\/block:\1\}/s';
    
        $template = preg_replace_callback($pattern, function ($matches) use ($data) {
            $blockName    = $matches[1];
            $blockContent = $matches[2];
            $result       = "";
    
            // If our current scope has data for this block (and is an array of rows)
            if (isset($data[$blockName]) && is_array($data[$blockName])) {
                foreach ($data[$blockName] as $row) {
                    // Merge the parent data with the current row data.
                    // This allows top-level variables to be available in a block if they aren’t overridden.
                    $merged = array_merge($data, $row);
                    // Recursively render the inner content for this row.
                    $result .= $this->renderTemplate($blockContent, $merged);
                }
            }
            // If no block data is available, simply remove the block region.
            return $result;
        }, $template);
    
        // Now replace any simple variable placeholders in the current template fragment.
        // Only scalar values (non-arrays) are substituted.
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $template = str_replace("{" . $key . "}", $value, $template);
        }
    
        return $template;
    }
}