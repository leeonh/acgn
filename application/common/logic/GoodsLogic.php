<?php
namespace app\common\logic;

use app\common\model\GoodsCategory;
use app\common\model\Goods;
use think\Config;
use think\Db;
use think\Session;

class GoodsLogic extends My_Logic{
    public function __construct(){
        $this->table = 'Goods';
    }
    //首页热门
    public function getlist(){
        $hot_goods = $cateList = array();
        $field="a.id,a.goods_name,a.original_img,a.goods_price,a.goods_type_id,b.name,b.pid_path";
        $where=['a.is_hot'=>1,'a.is_on_sale'=>1];
        $index_hot_goods=Db::name('goods')
                        ->alias('a')
                        ->join('goods_category b',' a.goods_type_id=b.id')
                        ->field($field)
                        ->where($where)
                        ->select();

        if ($index_hot_goods){
            foreach ($index_hot_goods as $val) {
                $path = explode('_',$val['pid_path']);
                $hot_goods[$path[1]][]=$val;
            }
        }
//        dump($hot_goods);
        $catetree = get_goods_category_tree();
        foreach ($catetree as $k=>$v){
            if($v['is_hot']==1){
                $v['hot_goods'] = empty($hot_goods[$k]) ? '' : $hot_goods[$k];
                $cateList[]=$goods_category_tree[] = $v;
            }else{
                $goods_category_tree[] = $v;
            }
        }
        return $cateList;
    }

    //商品列表
    public function getgoodslistBycateid($cateid,$level,$order){
        if ($cateid==0){
            $id = $this->get_all(['pid'=>$cateid,'is_show'=>1],'id','GoodsCategory');
            foreach ($id as $k=>$v) {
                $cate[]=$this->get_all(['pid'=>$v['id']],'id','GoodsCategory');
            }
            $str='';
            foreach ($cate as $kk=>$vv) {
                $str .= implode(',',array_column($vv, 'id')).",";
           }

            $res=Db::name('goods')->whereIn('goods_type_id',$str)->order($order)->paginate('16');

           return $res;
        }else{
            if ($level==2){
                $where = ['goods_type_id'=>$cateid];
                $res = Db::name('Goods')->where($where)->order($order)->paginate('16');
            }
            if ($level==1){
                $cate = $this->get_all(['pid'=>$cateid,'is_show'=>1],'id','GoodsCategory');
                $str = implode(',',array_column($cate, 'id'));
                $res=Db::name('goods')->whereIn('goods_type_id',$str)->order($order)->paginate('16');
            }
            return $res;
        }

    }

    //根据搜索框输入内容获取商品列表
    public function getgoodslistBysearch($search,$order){
        $res = Db::name('Goods')->where('goods_name','like',$search)->order($order)->paginate('16');
        return $res;
    }
    //商品展示
    public function goodslist($id){
        $goods = Db::name('goods')->where(['id'=>$id])->select();
        return $goods;
    }
    //获取商品分类并处理
    public function getcatelist(){
        $categoryList = Db::name('goods_category')->field('id,name,pid,level')->select();
        $nameList = array();
        foreach($categoryList as $k => $v)
        {
            $name = substr(get_letter($v['name']),0,1).' '. $v['name']; // 前面加上拼音首字母
            $nameList[] = $v['name'] = $name;
            $categoryList[$k] = $v;
        }
        array_multisort($nameList,SORT_STRING,SORT_ASC,$categoryList);
        return $categoryList;
    }
    //获取商品的所有属性
    public function get_all_goods_attr($goodsid){
        $res = Db::name('goods_attr')
            ->alias('a')
            ->join('goods_attribute s','a.attr_id=s.attr_id')
            ->where('goods_id',$goodsid)
            ->select();
        return $res;
    }

    //获取家族图谱数组
    public function getcatepath($cateid){
        $res = $this->get_one(['id'=>$cateid],'pid_path,level','GoodsCategory');
        return $res;
    }
    //获取编辑商品信息
    public function getgoodsinfo($id){
        $goods = Db::name('goods')->where(['id'=>$id])->select();
        $goods[0]['category'] = Db::name('goods_category')->where('id',$goods[0]['goods_type_id'])->find();
        return $goods;
    }

    //前端ajax改变商品的状态
    public function ajaxSetGoodsType($type,$id){
        if ($type=='isnew-notok'){
            $where=['id'=>$id];
            $data=[ 'is_new'=>0];
        }elseif ($type=='isnew-ok'){
            $where=['id'=>$id];
            $data=[ 'is_new'=>1];
        }elseif ($type=='ishot-notok'){
            $where=['id'=>$id];
            $data=[ 'is_hot'=>0];
        }elseif ($type=='ishot-ok'){
            $where=['id'=>$id];
            $data=[ 'is_hot'=>1];
        }elseif ($type=='isonsale-notok'){
            $where=['id'=>$id];
            $data=[ 'is_on_sale'=>0];
        }elseif ($type=='isonsale-ok'){
            $where=['id'=>$id];
            $data=[ 'is_on_sale'=>1];
        }
        $res = $this->edit($where,$data,'goods');
        return $res;

    }

    //根据goodsid获取goodstype商品模型
    public function getgoodstype($id){
        $key = Db::name('spec_goods_price')->where('goods_id',$id)->find();
        $itemid= explode('_',$key['key']);
        $typeid = Db::name('spec_item')->alias('a')
                ->join('spec s','a.spec_id=s.id')
                ->field('s.type_id')
                ->where('a.id',$itemid[0])
                ->find();
        return $typeid;
    }

    //根据categoryid获取分类信息
    public function getCateByid($id){
        $cate = Db::name('goods_category')->where(['id'=>$id])->find();
        return $cate;
    }

    //根据父类id获取分类信息
    public function getCateBypid($pid){
        $cate = Db::name('goods_category')->where(['pid'=>$pid])->select();
        return $cate;
    }

    //获取后台商品
    public function goodsAlllist($cateid,$isonsale,$type,$search){
        $where=array();
        if ($cateid){
            $where['a.goods_type_id']=$cateid;
        }
        if ($isonsale){
            if ($isonsale=="2"){
                $where['a.is_on_sale']=0;
            }else{
                $where['a.is_on_sale']=1;
            }
        }
        if ($type){
            if ($type=='new'){
                $where['a.is_new']=1;
            }else{
                $where['a.is_hot']=1;
            }
        }
        if ($search){
            $where['a.goods_name']=[ 'like', "%".$search."%"];
        }
        $goodslist = Db::name('goods')
                    ->alias('a')
                    ->field('a.id,a.goods_sn,a.goods_name,a.goods_price,a.goods_inventory,a.is_new,a.is_hot,a.is_on_sale,s.name')
                    ->join('goods_category s','a.goods_type_id = s.id')
                    ->where($where)
                    ->order('a.id desc')
                    ->paginate(5);
        return $goodslist;
    }

    //获取商品图片
    public function getimage(){
        $sql = "select a.id,a.goods_name,a.goods_price,a.goods_type_id,b.img_url from 
        ".config('database.prefix')."goods as a left join ".config('database.prefix')."goods_picture
        as b on a.id=b.goods_id order by a.goods_type_id;
        ";
        $res = Db::query($sql);
        return $res;
    }

    //获取添加到goods表的数据
    public function setarg1($data){
        //添加到goods表的数据
        if (isset($data['original_img'])){
            $args1=[
                'goods_name'=>$data['goods_name'],
                'goods_sn'=>$data['goods_sn'],
                'goods_inventory'=>$data['goods_inventory'],
                'goods_price'=>$data['goods_price'],
                'goods_abstract'=>$data['goods_abstract'],
                'goods_desc'=>$data['goods_desc'],
                'original_img'=>$data['original_img']
            ];
        }else{
            $args1=[
                'goods_name'=>$data['goods_name'],
                'goods_sn'=>$data['goods_sn'],
                'goods_inventory'=>$data['goods_inventory'],
                'goods_price'=>$data['goods_price'],
                'goods_abstract'=>$data['goods_abstract'],
                'goods_desc'=>$data['goods_desc']

            ];
        }

            if ($data['cate3']!=0){
                $x=3;
            }elseif ($data['cate2']!=0){
                $x=2;
            }else{
                $x=1;
            }
            switch ($x){
                case 1:
                    $args1['goods_type_id']=$data['cate1'];
                    break;
                case 2:
                    $args1['goods_type_id']=$data['cate2'];
                    break;
                case 3:
                    $args1['goods_type_id']=$data['cate3'];
                    break;
            }



        return $args1;
    }

    //获取商品规格
    public function getspec($id){
        //判断是否有规格项
        $check = $this->get_one(['goods_id'=>$id],'*','SpecGoodsPrice');
//        dump($check);
        if (empty($check)){
            return false;
        }
        $key = Db::name('spec_goods_price')->where("goods_id", $id)->column("GROUP_CONCAT(`key` ORDER BY store_count desc SEPARATOR '_') ");
        $spec = array();
        if ($key){
            $key = str_replace('_',',',$key);
            $keys = implode(',',$key);
            $sql = "select a.name,b.* from ".config('database.prefix')."spec as a inner join
            ".config('database.prefix')."spec_item as b on a.id=b.spec_id where b.id in ($keys) order by b.id";
            $spec2 = Db::query($sql);
            foreach ($spec2 as $v){
                $spec[$v['name']][] = array(
                    'item_id' => $v['id'],
                    'item' => $v['item'],
                );
            }
        }
        return $spec;
    }

    //商品添加时判断输入值
    public function checkaddgoods($data,$goodsid){
        if ($goodsid>0){
            $goodslist=Db::name('goods')->whereNotIn('id',$goodsid)->order('id')->select();

            if ($goodslist){
                foreach ($goodslist as $key=>$value){
                    if ($value['goods_name']==$data['goods_name']){
                        return (['status'=>0,'msg'=>'商品名字不能重复','data'=>$data['goods_name'],'shop'=>$value['goods_name']]);
                    }
                }
            }else{
                return (['status'=>2,'msg'=>'修改的商品已经失效']);
            }
        }else{
            $goods = new Goods();
            $goodslist= $goods->all();
            if ($goodslist){
                foreach ($goodslist as $key=>$value){
                    if ($value['goods_name']==$data['goods_name']){
                        return (['status'=>0,'msg'=>'商品名字不能重复','data'=>$data['goods_name'],'shop'=>$value['goods_name']]);
                    }
                }
            }else{
                return (['status'=>0,'msg'=>'model查询goods表出错了']);
            }
        }


    }

    //商品修改时判断商品名字是否重复
    public function checkgoodsname($id,$data){
        $goods = new Goods();
        $goodslist= $goods->whereNotIn('id',$id)->select();
        if ($goodslist){
            foreach ($goodslist as $key=>$value){
                if ($value['goods_name']==$data['goods_name']){
                    return (['status'=>0,'msg'=>'填写的商品名字重复了','data'=>$data['goods_name'],'shop'=>$value['goods_name']]);
                }
            }
        }else{
            return (['status'=>0,'msg'=>'没有查到数据']);

        }

    }

    //获取商品分类名称
    public function getcatenamebyId($id){
        $where['id'] = $id;
//        $catemodel = new GoodsCategory();
        $catename = Db::name('goods_category')->where(['id'=>$id])->field('name')->find();
        return $catename;
    }

    //修改商品相册
    public function addimagelist($id,$data){
        $imgIdStr = '';//商品相册id
        $res1=$res2=$res= false;
//        dump($data);
        foreach ($data as $key=>$val){
            $val['goods_id'] = intval($val['goods_id']);
            $where=[
                'img_url'=>$val['img_url'],
                'goods_id'=>intval($val['goods_id'])
            ];
            $check = $this->get_one($where,'*','GoodsPicture');
//            dump($check);
//            die;
            if($check){
                $imgIdStr.=$check['id'].',';
            }else{
                $res1=$this->add($where,'GoodsPicture');
                $imgIdStr.=$res1.",";
            }

        }
//        dump($imgIdStr);
//        die;
        if($imgIdStr){
            $res2=Db::name('goods_picture')->where('goods_id',$id)->whereNotIn('id',$imgIdStr)->delete();
        }
        if ($res1||$res2){
            $res=true;
        }
        return $res;
    }

    //添加或修改所有规格信息到specgoodsprice表
    public function addEditspecitem($id,$data){

        $keyArr = '';//规格key数组
        $res1=$res2=$res3=$res=false;
            foreach ($data as $key=>$val){
                $keyArr .= $key.',';
                $val['price'] = trim($val['price']);
                $val['store_count'] = intval($val['store_count']);
                $spec = [
                    'goods_id' => $id,
                    'key' => $key,
                    'key_name' => $val['key_name'],
                    'price' => $val['price'],
                    'store_count' => $val['store_count'],
                ];

                $specGoodsPrice = Db::name('spec_goods_price')->field('goods_id,key,key_name,price,store_count')->where(['goods_id' => $id, 'key' => $spec['key']])->find();

                if($specGoodsPrice){
//                        $res['addedit'] = Db::name('spec_goods_price')->where(['goods_id' => $id, 'key' => $key])->update($spec);
                         $result=Db::name('spec_goods_price')->where(['goods_id' => $id, 'key' => $key])->update($spec);
                        if ($result){
                            $res1=true;
                        }
//                    }
                }else{
                    //把每一项添加到sepcgoodsprice表
//                    $res['addedit'] = Db::name('spec_goods_price')->insert($spec);
                     Db::name('spec_goods_price')->insert($spec);
                    $res2=true;
                }
            }

        if($keyArr){
            $res3=Db::name('spec_goods_price')->where('goods_id',$id)->whereNotIn('key',$keyArr)->delete();

        }

        if ($res1||$res2||$res3){
            $res=true;
        }
        return $res;
    }

    //添加或修改所有规格信息到godos_attr表
    public function addEditattr($id,$data){
        $attr=array();
        $keyArr='';
        $res1=$res2=$res3=$res=false;
            foreach ($data as $key=>$val){
                $keyArr .= $key.',';
                foreach ($val as $v){
                    $attr = [
                        'attr_id' => $key,
                        'goods_id' => intval($id),
                        'attr_value' =>  $v,
                    ];
                    $att = Db::name('goods_attr')->field('attr_id,goods_id,attr_value')->where(['goods_id'=>$id,'attr_id'=>$key])->find();
                    if($att){
//
                            $arr[] = Db::name('goods_attr')->where(['goods_id' => $id, 'attr_id' => $key])->update($attr);
//
                        foreach ($arr as $v){
                           if ($v==1){
                               $res1['addedit']=1;
                           }

                        }
                    }else{
                        //把每一项添加到goodsattr表
//                        $res['addedit'] = Db::name('goods_attr')->insert($attr);
                        Db::name('goods_attr')->insert($attr);
                        $res2=true;
                    }
                }

            }
        if($keyArr){
            $res3= Db::name('goods_attr')->where('goods_id',$id)->whereNotIn('attr_id',$keyArr)->delete();
        }
        if ($res1||$res2||$res3){
            $res=true;
        }
//        return $res;
        return $res;

    }

    /**
     *删除商品规格操作
     * @param int $goods_id 商品id
     * @return bool
     */
    public function delspec($where=array()){
            $res = Db::name('spec_goods_price')->where($where)->delete();
            return $res;


    }
    /**
     *删除商品和属性操作
     * @param int $goods_id 商品id
     * @return bool
     */
    public function delattr($where=array()){
            $res = Db::name('goods_attr')->where($where)->delete();



            return $res;

    }


    /**
     * 动态获取商品属性输入框 根据不同的数据返回不同的输入框类型
     * @param int $goods_id 商品id
     * @param int $type_id 商品属性类型id
     */
    public function getAttrInput($goods_id,$type_id)
    {
        header("Content-type: text/html; charset=utf-8");
        $attributeList = Db::name('goods_attribute')->where(['type_id' => $type_id])->select();
        $str='';
        foreach($attributeList as $key => $val)
        {

            $curAttrVal = $this->getGoodsAttrVal(NULL,$goods_id, $val['attr_id']);
            //促使他 循环
            if(count($curAttrVal) == 0)
                $curAttrVal[] = array('goods_attr_id' =>'','goods_id' => '','attr_id' => '','attr_value' => '','attr_price' => '');
            foreach($curAttrVal as $k =>$v)
            {
                $str .= "<tr class='attr_{$val['attr_id']}'>";
                $addDelAttr = ''; // 加减符号
                // 单选属性 或者 复选属性
                if($val['attr_type'] == 1 || $val['attr_type'] == 2)
                {
                    if($k == 0)
                        $addDelAttr .= "<a onclick='addAttr(this)' href='javascript:void(0);'>[+]</a>&nbsp&nbsp";
                    else
                        $addDelAttr .= "<a onclick='delAttr(this)' href='javascript:void(0);'>[-]</a>&nbsp&nbsp";
                }

                $str .= "<td>$addDelAttr {$val['attr_name']}</td> <td>";


                // 手工录入
                if($val['attr_input_type'] == 0)
                {
                    $str .= "<input type='text' size='40' value='".($goods_id ? $v['attr_value'] : $val['attr_values'])."' name='attr_{$val['attr_id']}[]' />";
                }
                // 从下面的列表中选择（一行代表一个可选值）
                if($val['attr_input_type'] == 1)
                {
                    $str .= "<select name='attr_{$val['attr_id']}[]'><option value='0'>无</option>";
                    $tmp_option_val = explode(PHP_EOL, $val['attr_values']);
                    foreach($tmp_option_val as $k2=>$v2)
                    {
                        // 编辑的时候 有选中值
                        $v2 = preg_replace("/\s/","",$v2);
                        if($v['attr_value'] == $v2)
                            $str .= "<option selected='selected' value='{$v2}'>{$v2}</option>";
                        else
                            $str .= "<option value='{$v2}'>{$v2}</option>";
                    }
                    $str .= "</select>";
                  }
                // 多行文本框
                if($val['attr_input_type'] == 2)
                {
                    $str .= "<textarea cols='40' rows='3' name='attr_{$val['attr_id']}[]'>".($goods_id ? $v['attr_value'] : $val['attr_values'])."</textarea>";

                      }
                $str .= "</td></tr>";

            }

        }
        return  $str;
    }

    /**
     * 获取 tp_goods_attr 表中指定 goods_id  指定 attr_id  或者 指定 goods_attr_id 的值 可是字符串 可是数组
     * @param int $goods_attr_id goods_attr表id
     * @param int $goods_id 商品id
     * @param int $attr_id 商品属性id
     * @return array 返回数组
     */
    public function getGoodsAttrVal($goods_attr_id = 0 ,$goods_id = 0, $attr_id = 0)
    {
        $GoodsAttr = Db::name('goods_attr');
        if($goods_attr_id > 0)
            return $GoodsAttr->where("goods_attr_id = $goods_attr_id")->select();
        if($goods_id > 0 && $attr_id > 0)
            return $GoodsAttr->where("goods_id = $goods_id and attr_id = $attr_id")->select();
    }


    /**
     * 获取 规格的 笛卡尔积
     * @param $goods_id 商品 id
     * @param $spec_arr 笛卡尔积
     * @return string 返回表格字符串
     */
    public function getSpecInput($goods_id, $spec_arr)
    {

        // 排序
        foreach ($spec_arr as $k => $v)
        {
            $spec_arr_sort[$k] = count($v);
        }
        asort($spec_arr_sort);
        foreach ($spec_arr_sort as $key =>$val)
        {
            $spec_arr2[$key] = $spec_arr[$key];
        }


        $clo_name = array_keys($spec_arr2);

        $spec_arr2 = combineDika($spec_arr2); //  获取 规格的 笛卡尔积

        $spec = Db::name('spec')->column('id,name'); // 规格表

        $specItem = Db::name('spec_item')->column('id,item,spec_id');//规格项
        $keySpecGoodsPrice =  Db::name('spec_goods_price')->where('goods_id',$goods_id)->column('key,key_name,price,store_count');//规格项
//        dump($clo_name);
        $str = "<table class='table table-bordered' id='spec_input_tab'>";
        $str .="<tr>";
        $str_fill = "<tr>";
        // 显示第一行的数据
        foreach ($clo_name as $k => $v)
        {
            if ($v!=0){
                $str .=" <td><b>{$spec[$v]}</b></td>";
                $str_fill .=" <td><b></b></td>";
            }else{
               return false;
            }

        }

        $str .="<td><b>价格</b></td>
               <td><b>库存</b></td>
               <td><b>操作</b></td>
             </tr>";
        if(count($spec_arr2) > 0){
            $str_fill .='<td><input id="item_price" value="0" onkeyup="this.value=this.value.replace(/[^\d.]/g,&quot;&quot;)" onpaste="this.value=this.value.replace(/[^\d.]/g,&quot;&quot;)"></td>
              <td><input id="item_store_count" value="0" onkeyup="this.value=this.value.replace(/[^\d.]/g,&quot;&quot;)" onpaste="this.value=this.value.replace(/[^\d.]/g,&quot;&quot;)"></td>
               <td><button id="item_fill" type="button" class="btn btn-success">批量填充</button></td>
             </tr>';
            $str .= $str_fill;
        }
        // 显示第二行开始
        foreach ($spec_arr2 as $k => $v)
        {
            $str .="<tr>";
            $item_key_name = array();
            foreach($v as $k2 => $v2)
            {
                $str .="<td>{$specItem[$v2]['item']}</td>";
                $item_key_name[$v2] = $spec[$specItem[$v2]['spec_id']].':'.$specItem[$v2]['item'];

            }
            ksort($item_key_name);
            $item_key = implode('_', array_keys($item_key_name));
            $item_name = implode(' ', $item_key_name);
//            dump($item_key);
//            dump($keySpecGoodsPrice);
            $check=Db::name('spec_goods_price')->where(['goods_id'=>$goods_id,'key'=>$item_key])->find();
            if ($goods_id>0&&$check){
                $keySpecGoodsPrice[$item_key]['price'] ? false: $keySpecGoodsPrice[$item_key]['price']=0; // 价格默认为0
                $keySpecGoodsPrice[$item_key]['store_count'] ? false: $keySpecGoodsPrice[$item_key]['store_count']=0; //库存默认为0
                $str .="<td>
                    <input type='hidden' name='item[$item_key][key_name]' value='$item_name' />
                    <input name='item[$item_key][price]' value='{$keySpecGoodsPrice[$item_key]['price']}' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' /></td>";
                $str .="<td><input name='item[$item_key][store_count]' value='{$keySpecGoodsPrice[$item_key]['store_count']}' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")'/></td>";
                $str .="<td><button type='button' class='btn btn-default delete_item'>无效</button></td>";
                $str .="</tr>";
            }else{
                $keySpecGoodsPrice[$item_key]['price'] = 0; // 价格默认为0
                $keySpecGoodsPrice[$item_key]['store_count'] = 0; //库存默认为0
                $str .="<td>
                    <input type='hidden' name='item[$item_key][key_name]' value='$item_name' />
                    <input name='item[$item_key][price]' value='{$keySpecGoodsPrice[$item_key]['price']}' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")' /></td>";
                $str .="<td><input name='item[$item_key][store_count]' value='{$keySpecGoodsPrice[$item_key]['store_count']}' onkeyup='this.value=this.value.replace(/[^\d.]/g,\"\")' onpaste='this.value=this.value.replace(/[^\d.]/g,\"\")'/></td>";
                $str .="<td><button type='button' class='btn btn-default delete_item'>无效</button></td>";

                $str .="</tr>";
            }

        }
        $str .= "</table>";
        return $str;
    }
}
