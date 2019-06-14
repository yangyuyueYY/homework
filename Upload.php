<?php

namespace app\fileupload\controller;
use think\Controller;


class Upload extends Controller
{
    //显示主页
    public function index(){
        return $this->fetch();
    }
	//显示上传页面
    public function upload(){
        return $this->fetch();
    }
	/**
	 * 上传pom文件，解析至tree.txt
	 */
    public function upload_file(){
        // 获取表单上传文件
        $file = request()->file('maven');

        // 移动到框架应用根目录/public/uploads/ 目录下,文件名重复覆盖
        $info = $file->validate(['size'=>10485760,'ext'=>'xml'])->rule('uniqid')->move('/home/wwwroot/default/tp5/public/upload','');

        if($info){
            shell_exec('sudo bash /home/wwwroot/default/tp5/public/test.sh');
            //echo '写入tree成功';
            $treeInfo = $this->getTreeInfo();
            return array(
                "code"=>1,
                "data"=>json_encode($treeInfo));
        }else{
            // 上传失败获取错误信息
            echo $file->getError();
        }
    }
	
	public function restspid($info=''){
		$ch = curl_init();
		$res = array();
		if($info == ''){
			return array();
		}
		$url = $this->geturl($info);
		//curl_setopt($ch, CURLOPT_URL, "https://mvnrepository.com/artifact/junit/junit");
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$html = curl_exec($ch);
		curl_close($ch);
		$html = strstr($html, '<table class="grid versions" width="100%">');
		$pos = strpos($html, '</table>');
		$html = substr($html, 0, $pos+8);
		$info = explode('<tbody>', $html);
		$j = 0;
		$max = 0;
		$maxi = 0;
		if(is_array($info) && !empty($info)){
			array_splice ($info, 0, 1); 
			foreach($info as $k => $v){
				$info[$k] = explode('<tr>', $info[$k]);
				if(is_array($info[$k]) && !empty($info[$k])){
					foreach($info[$k] as $key => $val){
						$info[$k][$key] = explode('<td>',$info[$k][$key]);
						if(is_array($info[$k][$key]) && !empty($info[$k][$key])){
							if(count($info[$k][$key]) == 5){
								$t = strstr($info[$k][$key][1], '>');
								$pos = strpos($t, '</');
								$t = substr($t, 1, $pos-1);
								$res['info'][$j]['version'] = $t;
								$t = strstr($info[$k][$key][2], '>');
								$pos = strpos($t, '</');
								$t = substr($t, 1, $pos-1);
								$res['info'][$j]['Repository'] = $t;
								$t = strstr($info[$k][$key][3], '>');
								$pos = strpos($t, '</');
								$t = substr($t, 1, $pos-1);
								if($t == ''){
									$t = '0';
								}
								if(intval(str_replace(",","",$t)) > $max){
									$max = intval(str_replace(",","",$t));
									$maxi = $j;
								}
								$res['info'][$j]['Usages'] = $t;
								$pos = strpos($info[$k][$key][4], '</');
								$t = substr($info[$k][$key][4], 0, $pos);
								$res['info'][$j]['Date'] = $t;
								$j ++;
							}
						}
					}
				}
			}
		}
		$res['count'] = $j;
		$res['latest'] = $res['info'][0]['version'];
		$res['most'] = $res['info'][$maxi]['version'];
		//echo json_encode($res);
		return $res;
	}
	
	/**
	 * 获得软件包的直接依赖和间接依赖
	 */
	public function getDependency(){
		//读取tree.txt文件
		$filename = '/home/wwwroot/default/tp5/public/upload/tree.txt';
		$handle = fopen($filename, "r");//读取文件
		$result = array();
		//通过filesize获得文件大小，将整个文件一下子读到一个字符串中
		if(filesize($filename) > 0){
			$contents = fread($handle, filesize($filename));
		}
		fclose($handle);
		$i = 0;
		$flag = 0;

		$contents = explode("[INFO]", $contents);
		if(is_array($contents) && !empty($contents)){
			foreach($contents as $v){
				if($flag < 2){
					$flag ++;
				}else{
					$t = explode('"', $v);
					if(is_array($t) && !empty($t)){
						if($i == 0 ){
							$result['text'] = $t[1];
							$result['nodes'][$i]['text'] = $t[3];
							$i ++;
						}else{
							if($t[1] == $result['text']){
								$result['nodes'][$i]['text'] = $t[3];
								$i ++;
							}else{
								for($j=0;$j<$i;$j++){
									if($t[1] == $result['nodes'][$j]['text']){
										$result['nodes'][$j]['nodes'][]['text'] = $t[3];
										break;
									}
								}
								if($j >= $i){
									for($j=0;$j<$i;$j++){
										if(is_array($result['nodes'][$j]) && array_key_exists('nodes',$result['nodes'][$j])){
											if(is_array($result['nodes'][$j]['nodes']) && !empty($result['nodes'][$j]['nodes'])){
												foreach($result['nodes'][$j]['nodes'] as $key => $val){
													if($t[1] == $val['text']){
														$result['nodes'][$j]['nodes'][$key]['nodes'][]['text'] = $t[3];
														break;
													}
												}
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}
		echo json_encode($result);
	}

    /**
     * 获取树形结构信息
     */
	function getTreeInfo(){
        //读取tree.txt文件
        $filename = ROOT_PATH . 'public' . DS .'upload/tree.txt';
        $handle = fopen($filename, "r");//读取文件
        $tmp = array();
        $result = array();
        //通过filesize获得文件大小，将整个文件一下子读到一个字符串中
        if(filesize($filename) > 0){
            $contents = fread($handle, filesize($filename));
        }
        fclose($handle);
        $contents = explode("[INFO]", $contents);
        array_shift($contents);
        array_shift($contents);
        $img = '/tp5/public/static/images/icon-right.png';
        $count = 0;
        foreach($contents as $line){
            $line_arr = explode('"',$line);
            $parent = explode(':',$line_arr[1]);
            $parent_info = $parent[0].":".$parent[1].":".$parent[3];
            $child = explode(':',$line_arr[3]);
            $child_info = $child[0].":".$child[1].":".$child[3];
            $node = array(
                "id" => $child_info,
                "pid" => $parent_info,
                "text" => $child_info,
                "tags" => [0],
                "image" => $img,
            );
            $tmp[] = $node;
            $count++;
        }
        $result['info'] =  $this->genTree($tmp);
        $result['count'] = $count;
        return $result;
        //dump(json_encode($this->genTree($result)));
    }
    /*
     * 根据一维数组生成树形结构
     * */
    function genTree($items,$pid ="pid") {

        $map  = [];
        $tree = [];
        foreach ($items as &$it){ $map[$it['id']] = &$it; }  //数据的ID名生成新的引用索引树
        //dump($map);
        foreach ($items as &$it){
            $parent = &$map[$it[$pid]];
            if($parent) {
                $parent['nodes'][] = &$it;
                $parent['tags'][0] += 1;
            }else{
                $tree[] = &$it;
            }
        }
        foreach ($items as &$it){
            $it['tags'][0] = (string)$it['tags'][0];
        }
        return $tree;
    }
	
	function geturl($info, $flag=0){
		$res = "";
		$arr = explode(":", $info);
		if(is_array($arr) && count($arr)==3){
			$res .= "https://mvnrepository.com/artifact/".$arr[0].'/'.$arr[1];
			if($flag == 1){
				$res .= '/'.$arr[2];
			}
		}
		return $res;
	}
	
	public function getLicense($info){
		$ch = curl_init();
		$res = array();
		if($info == ''){
			return array();
		}
		$url = $this->geturl($info, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
		//curl_setopt($ch, CURLOPT_URL, "https://mvnrepository.com/artifact/junit/junit/4.11");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$html = curl_exec($ch);
		curl_close($ch);
		$html = strstr($html, '<span class="b lic">');
		$pos = strpos($html, '</td>');
		$html = substr($html, 0 , $pos);
		$info = explode('</span>', $html);
		if(is_array($info) && !empty($info)){
			foreach($info as $k => $v){
				$pos = strpos($info[$k], '>');
				$info[$k] = substr($info[$k], $pos+1);
			}
			array_pop($info);
		}
		return $info;
	}
}