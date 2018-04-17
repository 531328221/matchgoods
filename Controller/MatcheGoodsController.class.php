<?php
namespace Matche\Controller;
use Think\Controller;
use Match\Logic\RecordLogic;
use Think\Upload;

class MatcheGoodsController extends Controller {

    public function __construct() {
        parent::__construct();
    }

    public function displayImport(){
        $this->display('goods_import');
    }

    private function saveFile($file) {
        $name = explode(".",$file['file']['name']);
        $name = $name[0];
        $config = array(
            'maxSize'       =>  0, //上传的文件大小限制 (0-不做限制)
            'exts'          =>  array('xls','xlsx','xlsm','rar','zip'), //允许上传的文件后缀
            'rootPath'      =>  './Public/upload/', //保存根路径
            'driver'        =>  'LOCAL', // 文件上传驱动
            'subName'       =>  array('date', 'Ymd'),
            'saveName'      =>  $name . "_" . rand(100,999), //上传文件命名规则，[0]-函数名，[1]-参数，多个参数使用数组
            'savePath'      =>  "matchegoods/"
        ); 

        $upload = new Upload($config);
        return $rs = $upload->upload();

    }

    public function importRecord() {
        $fileInfo = $this->saveFile($_FILES);
        $file = "." . $fileInfo['file']['urlpath'];
        //$file =     './Public/upload/matchegoods/20180417/importGoods_988.xlsx';
        if(!file_exists($file)){
            $rs['status'] = false;
            $rs['msg'] = '该文件不存在，请再次确认';
            echo json_encode($rs);exit;
        }
        $brandTable = 'record_brand_atest';
        $goodsTable = 'record_goods_atest';
        $record = new RecordLogic($brandTable, $goodsTable);
        $rs = $record->import($file);
        echo json_encode($rs);exit;
    }

    public function goods_import() {
    	$this->display();
    }

    public function post_goods_import(){
    	if (!empty($_FILES)) {
            $name=$_FILES['file']['name'];
            $config = array(
                'maxSize'       =>  0, //上传的文件大小限制 (0-不做限制)
                'exts'          =>  array('xls','xlsx','xlsm','rar','zip'), //允许上传的文件后缀
                'rootPath'      =>  './Public/upload/', //保存根路径
                'driver'        =>  'LOCAL', // 文件上传驱动
                'subName'       =>  array('date', 'Ymd'),
                'saveName'      =>  $user_id.'_'.date("YmdHis"), //上传文件命名规则，[0]-函数名，[1]-参数，多个参数使用数组
                'savePath'      =>  "matchegoods/"
            ); 

            $upload = new Upload($config);

            $rs = $upload->upload($_FILES);

            if(!$rs){
                exit(json_encode(array('status' => 0,'msg' => '没有选择文件')));
            }else{
              $url="./Public/upload/".$rs['file']['savepath']."/".$rs['file']['savename'];
              $res=$this->order_data($url);
              if($res>0){
                exit(json_encode(array('status' => 1,'msg' => $res)));
              }else{
                exit(json_encode(array('status' => 0,'msg' => '导入失败，请检查表格填写是否规范!')));
              }
            }

        }else{
            exit(json_encode(array('status' => 0,'msg' => '没有选择文件')));
        }
    }


     public function order_data(/*$files*/){
     	$files =     './Public/upload/matchegoods/20180321/qy.xlsx';
        $save_file = './Public/upload/matchegoods/20180321/qy2.xlsx';
        $brand_table = 'record_brand_hz';
        $goods_table = 'record_goods_hz';
        if(!file_exists($files)){  
        echo  "该文件不存在，请再次确认"; exit;
        }  

        /*生成品牌（英文，缩写）*/
        $brandAll = M($brand_table)->order("p_id asc,id asc")->getField("id,brand,p_id",true);

        foreach ($brandAll as $k => $brand) {
        	$brandAll[$k]['brand'] = strtolower(str_replace(" ", "", $brand['brand']));
        }
        /*$brandMatrix = array_flip($brandAll);
        $brandMatrix[key($brandMatrix)] = 1;*/
        //var_dump($brandAll);exit;

        //表格行数获取
        $objPHPExcel = $this->HMTReadUpExcel($files);
        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow(); // 取得总行数
        $highestColumn = $sheet->getHighestColumn(); // 取得总列数

        $stat='0';
        $key_o=0;
        ini_set('max_execution_time', '500');
        $data=array();

        for($i=2;$i<=$highestRow;$i++){
          $end = $objPHPExcel->getActiveSheet()->getCell("E".$i)->getValue();//结束标记
          if(empty($end)) break;
          if($end!=$stat){

            $goodsName=strtolower(str_replace(" ", "",$objPHPExcel->getActiveSheet()->getCell("E".$i)->getValue()));
            $gcount = count(explode("，",$goodsName));
            if($gcount>=2){break;}
            //遍历的品牌
            foreach ($brandAll as $kb => $brand) {
                $brandArr = $brand;
            	$p_id = $brand['p_id'];
            	$brand = $brand['brand'];
  

            	//查找客户商品名称中是否含有遍历的品牌
            	$abbrArr = $this->mb_str_split($goodsName);
            	$abbr = $abbrArr[0].$abbrArr[1];


            	if($goodsName!=$brand && (strpos($goodsName,$brand)===0 || ($p_id>0&&$abbr==$brand)||((($p_id==0&&ord($abbrArr[0])<=122)||($p_id!=0&&ord($abbrArr[0])>122))&&strpos($goodsName,$brand)!==false) ))
            	{

            		$lastName = str_replace($brand,"",$goodsName);//查找出客户商品名称去掉品牌的剩余字符串
            		if($p_id){
            			$kb = $p_id;
            			$brand = $brandAll[$brandArr['p_id']]['brand'];
            		}

                    $charArr = array();
            		$charArr = $this->mb_str_split($lastName);//剩余字符串中文字符分割成数组
                    
            		$count = count($charArr);//剩余字符串 字符长度

            		//获取同品牌备案商品
                    //var_dump($kb,$brand);exit;
            		$rGoodsName = M($goods_table)->where("b_id=$kb")->getField("id,goods_name,weight",true);
            		$preName = array();//预输出的备案商品名称 
                    $precent100 = 0;
            		foreach ($rGoodsName as $kg => $goods) {
            			//过滤备案商品名称的空格 转小写，然后过滤品牌。顺序不能对调
            			$name = str_replace($brand,"",strtolower(str_replace(" ", "",$goods['goods_name'])));//备案商品名称
            			
            			$initWeight = $goods['weight'];//名字初始化权重1；
            			$weight = 0;
                        $totalWeightChild = 0;
                
            			//客户商品中最后一个字母必须和备案中的最后一个字母（中文字）一样才能获取权值，否则不设权值
            			/*if($charArr[$count-1]==end($this->mb_str_split($name))){
            				$weight += pow(10,$count)+$initWeight;
            			}*/
            			for ($ci=$count-1;$ci>=0;$ci--) {
            				$char = $charArr[$ci];
                            $totalWeightChild +=pow(2,($ci+1))+$initWeight;
                            if(pow(2,($ci+1))>10000){
                                var_dump($kb,$brand);exit;
                            }
                            
	            			if(strpos($name,$char)!==false){
	            				$weight += pow(2,($ci+1))+$initWeight;
	            			}
            			}
                        $weight!=0 && $weight == $totalWeightChild && $precent100= 1;
            			//防止权重值相同
            			if($weight>0){
	            			$w = 0;
                            $nums= 0;
	            			do{
	            				$nums =$weight-$w||0;//str_pad(($weight-$w),8,"0",STR_PAD_LEFT);
	            				$w++;
	            			}while(isset($preName[$nums]));
	            			$preName[$nums] = $goods['goods_name'];
	            		}
                        
            			//$rGoodsName[]['weight'] = $weight;
            		}
            		krsort($preName, SORT_NATURAL | SORT_FLAG_CASE);
            		//array(2) { ["00001001"]=> string(20) "Coach女式手提包" ["00001002"]=> string(15) "COACH 包挂饰" }
            		$preNameKey = array_keys($preName);

            		$pcount= count($preNameKey);
            		for ($pi=0;$pi<$pcount-1;) {
            			if($preNameKey[0]-$preNameKey[$pi+1]>=9){
            				break;
            			}
            			$pi++;
            		}
            		$preName2 = array();
            		for ($pii=0;$pii<=$pi;$pii++) {
            			$preName2[] = $preName[$preNameKey[$pii]];
            		}
            		
            		if(count($preName2)>1){
            			$this->sort_for_len($preName2);
            		}
                  

            		$precent100 ? $preName2=$preName2[0]: $preName2 = implode("/",$preName2);
            		if($preName2){
            			$objPHPExcel->getActiveSheet()->getStyle("F".$i)->getFont()->setColor(new \PHPExcel_Style_Color(\PHPExcel_Style_Color::COLOR_RED));
            			$objPHPExcel->getActiveSheet()->getCell("F".$i)->setValue($preName2);
            		}
                    if($precent100){break;}
            	}

            }

            $key_o++;
          }
          $stat=$end;
    	}

    $objPHPExcel->getActiveSheet()->setTitle('sheet1');
    $objPHPExcel->setActiveSheetIndex(0);
    $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel,"Excel5");
    $objWriter->save($save_file);
    echo 'ok';exit;
    return true;

    }

    public function mb_str_split($string) {
    	return preg_split('/(?<!^)(?!$)/u', $string);
    }

    public function sort_for_len(&$arr){
    	$len = count($arr);
    	$turn;
    	for ($j=$len-1; $j >=1; $j--) { 
    		for ($i=$j; $i >=1; $i--) { 
	    		if(strlen($arr[$i])<strlen($arr[$i-1])){
	    			$temp  = $arr[$i];
	    			$arr[$i] = $arr[$i-1];
	    			$arr[$i-1] = $temp;
	    		}
	    	}
    	}
    	return $arr;
    	
    }

    private function HMTReadUpExcel($file){

      require_once THINK_PATH.'Library/Vendor/PHPExcel/PHPExcel.php';
      require_once THINK_PATH.'Library/Vendor/PHPExcel/PHPExcel/IOFactory.php';
      return \PHPExcel_IOFactory::load($file);
    }


}