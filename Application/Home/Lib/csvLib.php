<?php

class csvLib
{
    public $f_out;
    public $data;
    public $file_name;
    function __construct($file_name)
    {
        $this->f_out = $this->open_output();
        $this->file_name = $file_name;
        //$this->data = $data;
    }
    public function open_output(){
        return fopen('php://output', 'w');
    }

    function printHeaderInfo(){
        header('Content-Type: application/vnd.ms-excel');
        header("Content-Disposition: attachment;filename=$this->file_name");
        header('Cache-Control: max-age=0');
        header('Expires: 0');
        header('Cache-Control: cache, must-revalidate');
        header('Pragma: public');
    }

    function exportCsv($data){
        foreach ($data as $item) {
            foreach ($item as &$column) {
                $column = iconv("utf-8", "gbk//IGNORE", $column);
            }
            fputcsv($this->f_out, $item);
            //ob_flush();

        }
    }

    function csvHeader($header = []){
        foreach ($header as &$column) {
            $column = iconv("utf-8", "gbk//IGNORE", $column);
        }
        fputcsv($this->f_out, $header);
    }

    function closeFile()
    {
        fclose($this->f_out);
    }

    
}
?>