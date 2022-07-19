<?php

declare(strict_types=1);

namespace app\common\library;

use PhpOffice\PhpSpreadsheet\Shared\Date;
use think\file\UploadedFile;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use app\common\exception\ServiceException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\BaseReader;

/**
 * 表格读取
 */
class ReadList
{
    /**
     * @var string|UploadedFile
     */
    protected $filePath = '';

    protected $fieldArr = [];

    /**
     * 分批处理数量
     *
     * @var integer
     */
    protected $chunk_num = 0;

    /**
     * 处理函数
     *
     * @var callable
     */
    protected $chunk_callable;

    /**
     * 读取的开始行数
     *
     * @var integer
     */
    protected $start_read_line = 1;

    /**
     * 设置文件路径
     *
     * @param string|UploadedFile $filePath
     * @return $this
     */
    public function setFilePath($filePath)
    {
        if (!$filePath) {
            throw new ServiceException('文件不存在');
        }
        $this->filePath = $filePath;
        return $this;
    }

    /**
     * 设置字段对应
     *
     * @param array $fieldArr
     * 
     * [
     *    '表格表头字段' => '自定义键名'
     * ]
     * 
     * @return $this
     */
    public function setFieldArr(array $fieldArr = [])
    {
        $this->fieldArr = $fieldArr;
        return $this;
    }

    /**
     * 设置批处理
     *
     * @param integer $chunk_num
     * @param callable $chunk_callable
     * @return $this
     */
    public function setChunk(int $chunk_num, callable $chunk_callable)
    {
        $this->chunk_num = $chunk_num;
        $this->chunk_callable = $chunk_callable;
        return $this;
    }

    /**
     * 读取数据
     *
     * @return array
     */
    public function readList(): array
    {
        $fieldArr = $this->fieldArr;
        $filePath = $this->filePath;
        $reader = $this->getReader();
        $insert = [];
        if (!$PHPExcel = $reader->load($filePath)) {
            throw new ServiceException('文件加载失败');
        }
        $currentSheet = $PHPExcel->getSheet(0);  //读取文件中的第一个工作表
        $allColumn = $currentSheet->getHighestDataColumn(); //取得最大的列号
        $allRow = $currentSheet->getHighestRow(); //取得一共有多少行
        $maxColumnNumber = Coordinate::columnIndexFromString($allColumn);
        $fields = [];
        for ($currentRow = $this->start_read_line; $currentRow <= $this->start_read_line; $currentRow++) {
            for ($currentColumn = 1; $currentColumn <= $maxColumnNumber; $currentColumn++) {
                $val = $currentSheet->getCellByColumnAndRow($currentColumn, $currentRow)->getValue();
                $fields[] = $val;
            }
        }
        for ($currentRow = $this->start_read_line + 1; $currentRow <= $allRow; $currentRow++) {
            $values = [];
            for ($currentColumn = 1; $currentColumn <= $maxColumnNumber; $currentColumn++) {
                $val = $currentSheet->getCellByColumnAndRow($currentColumn, $currentRow)->getValue();
                $values[] = is_null($val) ? '' : $val;
            }
            $row = [];
            $temp = array_combine($fields, $values);
            foreach ($temp as $k => $v) {
                if (isset($fieldArr[$k]) && $k !== '') {
                    $row[$fieldArr[$k]] = $v;
                }
            }
            if ($row) {
                $insert[] = $row;
            }
            if ($this->isSetChunk() && count($insert) === $this->chunk_num) {
                call_user_func($this->chunk_callable, $insert);
                $insert = [];
            }
        }

        if ($this->isSetChunk() && count($insert) > 0) {
            call_user_func($this->chunk_callable, $insert);
            $insert = [];
        }
        return $insert;
    }

    /**
     * 是否设置分批处理
     */
    public function isSetChunk(): bool
    {
        return $this->chunk_num > 0 && is_callable($this->chunk_callable);
    }

    /**
     * 获取 BaseReader
     *
     * @return BaseReader
     */
    public function getReader(): BaseReader
    {
        $filePath = $this->filePath;
        //实例化reader
        $ext = $filePath instanceof UploadedFile ? $filePath->extension() : pathinfo($filePath, PATHINFO_EXTENSION);
        if (!in_array($ext, ['csv', 'xls', 'xlsx'])) {
            throw new ServiceException('文件格式不正确');
        }
        if ($ext === 'xls') {
            $reader = new Xls();
        } else {
            $reader = new Xlsx();
        }
        return $reader;
    }

    public static function excelToTimestamp($v)
    {
        return Date::excelToTimestamp($v,'Asia/Shanghai');
    }

    /**
     * 设置开始读取行数（从表头算起）
     * 
     * @param integer $start_read_line
     * @return $this
     */
    public function setStartWriteLine(int $start_read_line)
    {
        if ($start_read_line <= 0) {
            throw new \Exception('start_read_line min 2');
        }
        $this->start_read_line = $start_read_line;
        return $this;
    }
}
