<?php
Vendor('PHPExcel.Classes.PHPExcel');
Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel2007.php');
Vendor('PHPExcel.Classes.PHPExcel.Reader.Excel5.php');

class silver
{


    /*
     * ------------------------------------------
     * PHPExcel处理导入的excel文件数据
     * Author：jk        Time：2016-11-29
     * ------------------------------------------
     */
    public function index($filePath)
    {
        set_time_limit(90);
        $PHPExcel = new \PHPExcel();
        # @默认用excel2007读取excel，若格式不对，则用之前的版本进行读取
        $PHPReader = new \PHPExcel_Reader_Excel2007();
        if (!$PHPReader->canRead($filePath)) {
            $PHPReader = new \PHPExcel_Reader_Excel5();
            if (!$PHPReader->canRead($filePath)) {
                return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
            }
        }
        $PHPExcel = $PHPReader->load($filePath);
        # @读取excel文件中的第一个工作表
        $currentSheet = $PHPExcel->getSheet(0);
        # @取得最大的列号
        $allColumn = $currentSheet->getHighestColumn();
        # @取得一共有多少行
        $allRow = $currentSheet->getHighestRow();
        # @防止列头查过26个字母
        ++$allColumn;
        $erp_orders_id = array();
        # @从第二行开始输出，因为excel表中第一行为列名
        for ($currentRow = 1; $currentRow <= $allRow; $currentRow++) {
            for ($currentColumn = 'A'; $currentColumn != $allColumn; $currentColumn++) {
                $address = $currentColumn . $currentRow;
                $erp_orders_id[$currentRow][$currentColumn] = $currentSheet->getCell($address)->getValue() == null ? '' : $currentSheet->getCell($address)->getValue();
            }
        }

        return ['status' => 1, 'info' => $erp_orders_id, 'message' => 'excel数据转数组成功！'];
    }


    /*
     * ------------------------------------------
     * excel文件上传
     * Author：jk        Time：2016-11-30
     * ------------------------------------------
     */
    public function uploadFile($upload_dir, $file, $file_name)
    {
        @set_time_limit(5 * 60);
        if (!empty(trim($file['tmp_name'])) && !empty(trim($file['type'])) && intval($file['size']) > 0) {
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            if ($file["error"] || !is_uploaded_file($file["tmp_name"])) {
                return '上传文件有误';
            }
            if (!move_uploaded_file($file['tmp_name'], trim($upload_dir . $file_name))) {
                return '上传失败';
            } else {
                return '上传成功';
            }
        }
    }

    /* ------------------------------------------
    * PHPExcel处理导入的excel文件数据
    * Author：xiaowen        Time：2016-11-29
    * ------------------------------------------
    */
    public function wxalipay($filePath, $is_wx)
    {
        set_time_limit(90);
        $PHPExcel = new \PHPExcel();
        # @默认用excel2007读取excel，若格式不对，则用之前的版本进行读取
        $PHPReader = new \PHPExcel_Reader_Excel2007();
        if (!$PHPReader->canRead($filePath)) {
            $PHPReader = new \PHPExcel_Reader_Excel5();
            if (!$PHPReader->canRead($filePath)) {
                return ['status' => 2, 'info' => [], 'message' => '文件不存在！'];
            }
        }
        $PHPExcel = $PHPReader->load($filePath);
        # @读取excel文件中的第一个工作表
        $currentSheet = $PHPExcel->getSheet(0);
        # @取得最大的列号
        $allColumn = $currentSheet->getHighestColumn();
        # @取得一共有多少行
        $allRow = $currentSheet->getHighestRow();
        # @防止列头查过26个字母
        ++$allColumn;
        $erp_orders_id = array();
        # @从第二行开始输出，因为excel表中第一行为列名
        for ($currentRow = 1; $currentRow <= $allRow; $currentRow++) {
            for ($currentColumn = 'A'; $currentColumn != $allColumn; $currentColumn++) {
                if ($is_wx == 2 && $currentColumn == 'A' && $currentRow != 1) { //微信导入 需要转换excel的时间

                    $address = $currentColumn . $currentRow;
                    //gmdate("Y-m-d H:i:s", PHPExcel_Shared_Date::ExcelToPHP($currentSheet->getCell($address)->getValue()));
                    $erp_orders_id[$currentRow][$currentColumn] = $currentSheet->getCell($address)->getValue() == null ? '' : gmdate("Y-m-d H:i:s", PHPExcel_Shared_Date::ExcelToPHP($currentSheet->getCell($address)->getValue()));
                    //$erp_orders_id[$currentRow][$currentColumn] = $currentSheet->getCell($address)->getValue() == null ? '' : $currentSheet->getCell($address)->getValue();

                } else {
                    $address = $currentColumn . $currentRow;
                    $erp_orders_id[$currentRow][$currentColumn] = $currentSheet->getCell($address)->getValue() == null ? '' : $currentSheet->getCell($address)->getValue();
                }

            }
        }

        return ['status' => 1, 'info' => $erp_orders_id, 'message' => 'excel数据转数组成功！'];
    }


}


?>
