<?php

namespace Matche\Logic;


class RecordLogic 
{

	public $brandTable;
	public $goodsTable;
	public $result = ['status'=>true, 'msg'=>''];

	public function __construct($brandTable, $goodsTable){
		ini_set('max_execution_time', '500');
		$this->brandTable = $brandTable;//'record_brand_atest';
		$this->goodsTable = $goodsTable;//'record_goods_atest';
	}


	public function import($file){
		$rs = ['status'=>true,'msg'=>''];
		$brandTable = $this->brandTable;//导入的品牌表
        $goodsTable = $this->goodsTable;//导入的备案商品
        $sheetIndex = 0;//默认获取的excel表0
        $goodsCol = 'A';//a列为商品名称
        $brandCol = 'B';//b列为品牌列
        $startRow = 2;//第二行
        $highestRow;//excel中行数
        $highestColumn;//excel中最大列数
        $data = [];//excel导出的所有数据存放
        $errorInfo = [];
        

        //读取excel
        $objPHPExcel = $this->HMTReadUpExcel($file);
        $activeSheet = $objPHPExcel->getSheet($sheetIndex);
        $highestRow = $activeSheet->getHighestRow();
        $highestColumn = $activeSheet->getHighestColumn();

        //开始循环获取数据
        
        for ($currentR=2; $currentR <= $highestRow; $currentR++) { //$highestRow
        	$goodsNameOld = $activeSheet->getCell($goodsCol.$currentR)->getValue();
        	$goodsNameOld = trim($goodsNameOld);
        	$brandNameOld = $activeSheet->getCell($brandCol.$currentR)->getValue();
        	$brandNameOld = trim($brandNameOld);
        	if( !$goodsNameOld || !$brandNameOld ) {
        		$rs['msg'] = " 共执行{($currentR-2)}条记录！";
        		break;
        	}
        	$brandName = $this->formattingBrand($brandNameOld);//array
        	//新增品牌，（有则返回id，无则新增返回）id为顶层（p_id=0）的品牌，即商品需要绑定的b_id
        	$brandId = $this->insertBrand($brandName);
        	$goodsId = $this->insertGoods($goodsNameOld, $brandId, $brandNameOld);
        	//$goodsName = $this->formattingCommon($goodsNameOld);
        	$recodId = $currentR-1;
        	if(!$brandId){
        		$errorInfo[$currentR-2]['brand'] = "第{$recodId}条记录的{$brandNameOld}品牌添加失败！";
        	}
        	if(!$goodsId){
        		$errorInfo[$currentR-2]['goods'] = "第{$recodId}条记录的{$goodsNameOld}备案商品添加失败！";
        	}
        	if($goodsId==-1){
        		$errorInfo[$currentR-2]['goods'] = "第{$recodId}条记录的{$goodsNameOld}备案商品已存在！";
        	}
  
        }//end for highestRow

        if( !empty($errorInfo) ){
        	$rs['status'] = false;
        	$rs['msg'] .= $this->makeErrorInfo($errorInfo);
        }else{
        	$rs = ['status'=>true, 'msg'=>"成功！"];
        }

        return $rs;

	}

	private function makeErrorInfo($errorInfo){
		$msg = '';
    	foreach ($errorInfo as  $error) {
    		$msg .= $error['brand'] . $error['goods'] ."\n";
    	}

    	return $msg;

	}

	private function insertGoods($goodsName, $b_id, $brandNameOld){
		$table = $this->goodsTable;
		$map['goods_name'] = $goodsName;
		$find = M($table)->where($map)->count()?-1:0;
		$data = [
			'goods_name' => $goodsName,
			'b_id' => $b_id,
			'brand_name' => $brandNameOld
		];
		$rs = $find? $find : M($table)->add($data);
		return $rs;

	}

	//格式化品牌，获取多级品牌 返回数组
	private function formattingBrand($cell){
		$cell = $this->formattingCommon($cell);
		$Arr = explode("/",$cell);
		$cellArr = [];
		foreach ($Arr as $brand) {
			$merge = [];
			$merge['brand'] = $brand;
			$cellArr[] = $merge;
		}
		return $cellArr;
	}

	//对比品牌 无则插入，有就返回记录
	private function insertBrand($brandName){
		$table = $this->brandTable;
		$p_id = 0;
		//遍历所有的品牌查看pid ，标记已存在
		foreach ($brandName as $bk => $brand) {
			$brandInfo = $this->getBaseBrand($brand['brand']);
			$brandName[$bk]['is_exit'] = $brandInfo ? 1 : 0;
			if(!$p_id){
				$brandInfo && $p_id = $brandInfo['id'];
			}elseif($p_id != $brandInfo['id'] && $brandInfo['id'] != 0){//存在品牌记录里多个品牌，但是他们p_id不相同
				return false;
			}
		}
		
		//品牌不存在则新增，如果pid为0时 生成的第一条品牌记录id为pid
		foreach ($brandName as $brand) {
			if( !$brand['is_exit'] ){
				$id = M($table)->add([
					'brand' => $brand['brand'],
					'p_id' => $p_id
				]);
				!$p_id && $p_id = $id;
			}
		}

		return $p_id;
	}

	//格式化 excel字符,去空格，大小写
	private function formattingCommon($cell){
		$cell = $this->filterBlank($cell);
		return strtolower($cell);
	}

	//去空格
	private function filterBlank($cell){
		$filterMap = [" ", "\t", "\n", "\r", "\0", "\x0B"];
		return str_replace($filterMap, '', $cell);
	}

	//查找底层品牌,没有父类品牌则返回当前品牌
	private function getBaseBrand($brandName){
		$table = $this->brandTable;
		$map = [];
		if( is_numeric($brandName) ) {
			$map['id'] = $brandName;
		} else {
			$map['brand'] = $brandName;
		}
		
		$brand = M($table)->where($map)->find();
		if($brand['p_id']) {
			return $this->getBaseBrand($brand['p_id']);
		} else {
			return $brand;
		}
	}

	private function HMTReadUpExcel($file){

      require_once THINK_PATH.'Library/Vendor/PHPExcel/PHPExcel.php';
      require_once THINK_PATH.'Library/Vendor/PHPExcel/PHPExcel/IOFactory.php';
      return \PHPExcel_IOFactory::load($file);
    }

}