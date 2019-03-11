<?php
namespace app\admin\controller;



use app\common\logic\GoodsLogic;
use app\common\logic\SpecLogic;
use think\Config;
use think\Cookie;
use think\Db;
use think\Loader;
use think\File;
use think\Session;
use think\Validate;
//use app\admin\validate\Goods;

class Goods extends Base
{
    public $path = '/public/uploads/';//文件保存路劲开头

    public function _initialize()
    {
        return parent::_initialize(); // TODO: Change the autogenerated stub
    }

    public function test()
    {
    $this->assign('role',1);
        return $this->fetch();


    }


    public function index()
    {
        $goodslogic = new GoodsLogic();
        $goodslist = $goodslogic->goodsAlllist();
        $page = $goodslist->render();//分页页码
        $this->assign('goodslist',$goodslist);
        $this->assign('page',$page);
        return $this->fetch();


    }
    //添加商品页面
    public function add()
    {
        $goodsid=0;
        $catetree = Db::name('goods_category')->where("pid = 0")->select();
        $goodsmodel = Db::name('goods_type')->order('tid')->select();
        $this->assign('cate1',$catetree);
        $this->assign('goodsmodel',$goodsmodel);
        $this->assign('goods',$goodsid);
        return $this->fetch();


    }

    //获取修改商品页面
    public function goodsinfo(){
        $id = input('id');

        $get = new GoodsLogic();
        $goodsinfo = $get->getgoodsinfo($id);
        //根据pid_info获取分类的所有父类pid
        foreach ($goodsinfo as $key=>$value){
            $path = explode('_',$value['category']['pid_path']);
            $goodsinfo[$key]['cate1']=$path[1]?$get->getCateByid($path[1]):'';
            $goodsinfo[$key]['cate2']=$path[2]?$get->getCateByid($path[2]):'';
//            $goodsinfo[$key]['cate3']=$path[3]?$get->getCateByid($path[3]):'';
        }

//        dump($goodsinfo);
        if ($goodsinfo[0]['cate1']&&$goodsinfo[0]['cate2']){
            $cate2 = $get->getCateBypid($goodsinfo[0]['cate1']['id']);
            $this->assign('cate2',$cate2);
        }
        //获取该商品的模型
        $logic=new GoodsLogic();
        $goodstypeid = $logic->getgoodstype($id);
        $catetree = Db::name('goods_category')->where("pid = 0")->select();
        //获取所有模型
        $goodsmodel = Db::name('goods_type')->order('tid')->select();
        $this->assign('cate1',$catetree);
        $this->assign('goodsinfo',$goodsinfo);
        $this->assign('goodsmodel',$goodsmodel);
        $this->assign('goodstypeid',$goodstypeid);
        $this->assign('goods',$id);
        return $this->fetch();
    }

//获得2 3级分类
    public function changelevelcate(){
        $cateid = input('cate');
        $catetree = Db::name('goods_category')->where(['pid'=>$cateid,'is_show'=>1])->order('id desc')->select();
        if($catetree){
            return json(['status'=>1,'msg' => '获取成功！','result'=>$catetree]);
        }else{
//            dump($cateid);
            return json(['status' => -1, 'msg' => '获取失败！', 'result' =>[]]);
        }
//        dump($cateid);



    }

    //根据模型id获取商品规格
    public function getspecitem(){
        $goods_id = input('goods_id/d') ? input('goods_id/d') : 0;
        $typeid = input('id');
        $logic = new SpecLogic();
        $spec= $logic->getgoodsaddoneSpeclist($typeid);
        foreach($spec as $k => $v){
            $spec[$k]['spec_item'] = Db::name('spec_item')->where("spec_id",$v['id'])->order('id')->column('id,item'); // 获取规格项
        }

        if ($goods_id){
            $items_id = Db::name('spec_goods_price')->where("goods_id", $goods_id)->column("GROUP_CONCAT(`key` SEPARATOR '_') AS items_id");
//            dump($items_id);
            $items_ids = explode('_', $items_id[0]);
            $this->assign('items_ids',$items_ids);
        }
//        dump($spec);
        $this->assign('specList',$spec);
        return $this->fetch('spec_select');
    }

    //根据模型id获取商品属性
    public function getattr(){
        $GoodsLogic = new GoodsLogic();
        $str = $GoodsLogic->getAttrInput($_REQUEST['goods_id'],$_REQUEST['id']);
        exit($str);
    }

    /**
     * 动态获取商品规格输入框 根据不同的数据返回不同的输入框
     */
    public function ajaxGetSpecInput(){
        $spec_arr = input('spec_arr/a',[[]]);
//        dump($spec_arr);
        $GoodsLogic = new GoodsLogic();
        $goods_id = input('goods_id/d') ? input('goods_id/d') : 0;
        $str = $GoodsLogic->getSpecInput($goods_id ,$spec_arr);
        exit($str);
    }

    //添加修改商品
    public function addEditgoods(){
        $data = input('post.');
//        dump($data);
       //获取商品属性值
        $goodsid=$data['goods_id'];
//        dump($data);

        foreach($data as $key=>$val ){
            if(preg_match('/^attr.*/',$key)) {
               $attr[ltrim($key,"attr_")]=$val;//添加到attribute表的数据
            }
        }
        $file =request()->file('original_img');
        //图片
        if($file){
            $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads');
            if($info){
                // 成功上传后 获取上传信息
                $filePathname = $info->getSaveName();
//                $data['original_img']='/public/uploads/'.$filePathname;
                $data['original_img']=$this->path.str_replace('\\', '/', $filePathname);

            }else{
                // 上传失败获取错误信息
                echo $file->getError();
            }
        }else{
            if ($goodsid==0){
                return json(['status'=>0,'msg'=>'商品图片不能为空']);
            }
        }
        $goodslogic = new GoodsLogic();
        //判断商品是否重复添加
        $res = $goodslogic->checkaddgoods($data,$goodsid);
        if ($res){
            if ($res['status']==0){
//                return json($res);
                $this->error($res['msg'],url('Admin/goods/index'));
            }

        }
        //获取1级商品分类名称
//        $catename1 = $goodslogic->getcate1namebyId($data['cate1']);
        $catename1 = mb_substr($goodslogic->getcatenamebyId($data['cate1'])['name'],0,2);
        $num = get_letter($catename1);

        $validate = Loader::validate('Goods');
        if(!$validate->check($data)){
            return json(['status'=>0,'msg'=>$validate->getError()]);
        }else{
            $args1 = $goodslogic->setarg1($data);
            if ($goodsid==0){
                $args1['goods_time']=time();
            }



            Db::startTrans();
                try{
                    //如果有商品id则执行更新操作
                    if ($goodsid>0){
                        //执行修改操作
                        $update = Db::name('goods')->where('id',$goodsid)->update($args1);

                            //添加商品规格操作
                            if (isset($data['item'])){
                                    $res1=$goodslogic->addEditspecitem($goodsid,$data['item']);
//                                    if (!$res1){
//                                        $this->error('写入商品规格失败',url('Admin/goods/goodsinfo',array('id'=>$goodsid)),1);
//                                    }
                            }
                            //添加商品属性操作
                            if (isset($attr)) {
                                    $res2 = $goodslogic->addEditattr($goodsid, $attr);
//                                    if (!$res2) {
//                                        $this->error('写入商品属性失败', url('Admin/goods/goodsinfo',array('id'=>$goodsid)), 1);
//                                    }
                            }
                        if ($update||$res1['addedit']||$res1['del']||$res2['addedit']||$res2['del']){
                            Db::commit();
                            $this->success('商品修改成功',url('Admin/goods/index'),1);
                        }else{
//                                dump($res2);
                            return  "<script>alert('修改商品失败');window.location.href='/Admin/Goods/goodsinfo/id/$goodsid.html';</script>";
//                            $this->error('修改商品失败',url('Admin/goods/goodsinfo',array('id'=>$goodsid)));
                        }
                    }
                    $id = Db::name('goods')->insertGetId($args1);
                    if ($id){
                        //如果没有填写goods_sn
                        if (empty($data['goods_sn'])){
                            $sn1= $num.$id;
                            $sn2='';
                            $length = strlen($sn1);
                                for ($i=$length;$i<9;$i++){
                                    $sn2.='0';
                                }
                            $sn = $num.$sn2.$id;
                            $args = ['goods_sn'=>$sn];
                            $res = Db::name('goods')->where('id',$id)->update($args);
                            if ($res){
                                //添加商品规格操作
                                if (isset($data['item'])){
                                    $res1=$goodslogic->addEditspecitem($id,$data['item']);
                                    if (!$res1){
                                        $this->error('写入商品规格失败',url('Admin/goods/index'),1);
                                    }
                                }
                                //添加商品属性操作
                                if (isset($attr)){
                                    $res2=$goodslogic->addEditattr($id,$attr);
                                    if (!$res2){
                                        $this->error('写入商品属性失败',url('Admin/goods/index'),1);
                                    }
                                }
                                Db::commit();
                                $this->success('商品添加成功',url('Admin/goods/index'),1);
                            }else{
//                            return json(['status'=>0,'msg'=>'自动写入商品编号失败']);
                                $this->error('自动写入商品编号失败',url('Admin/goods/add'),1);
                            }
                        }
                    }else{
                        $this->error('添加商品失败');
                    }
                }catch (\PDOException $e){
                    Db::rollback(); //回滚事务
                }

        }


    }



//    //修改商品
//    public function updategoods(){
//        $data = input('post.');
//        $id = Session::get('goodsid');
//
//        $data=request()->param();
//        $file =request()->file('original_img');
////        dump($data['id']);
////        exit();
//        //图片
//        if($file){
//            $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads');
//            if($info){
//                // 成功上传后 获取上传信息
//                $filePathname = $info->getSaveName();
////                $data['original_img']='/public/uploads/'.$filePathname;
//                $data['original_img']=$this->path.str_replace('\\', '/', $info->getSaveName());
//
//            }else{
//                // 上传失败获取错误信息
//                echo $file->getError();
//            }
//        }
//
//
//
//        $goodslogic = new GoodsLogic();
//        //获取1级商品分类名称
//        $catename1 = mb_substr($goodslogic->getcatenamebyId($data['cate1'])['name'],0,2);
//        $num = get_letter($catename1);
//        //判断商品名字是否重复
//        $res = $goodslogic->checkgoodsname($id,$data);
//        if ($res){
//            if ($res['status']==0){
////                return json($res);
//                $this->error($res['msg'],url('Admin/Goods/index'));
//            }
//        }
//        if ($data){
//            if ($data['cate3']!=0){
//                $x=3;
//            }elseif ($data['cate2']!=0){
//                $x=2;
//            }else{
//                $x=1;
//            }
//
//            switch ($x){
//                case 1:
//                    $data['goods_type_id']=$data['cate1'];
//                    break;
//                case 2:
//                    $data['goods_type_id']=$data['cate2'];
//                    break;
//                case 3:
//                    $data['goods_type_id']=$data['cate3'];
//                    break;
//                default :
//                    $data['goods_type_id']='';
//            }
//
//        }
//
////        dump($data);
//        //validate验证
//        $validate = Loader::validate('AdminUpdateGoods');
//        if(!$validate->check($data)){
//            return json(['status'=>0,'msg'=>$validate->getError()]);
//        }else{
//            $data['goods_time']=time();
//            unset($data['cate1']);
//            unset($data['cate2']);
//            unset($data['cate3']);
//
//
//                if (empty($data['goods_sn'])){
//
////                    $sn1= substr($num.'0000000'.$id,-3,9);
//                    $sn1= $num.$id;
//                    $sn2='';
//                    $length = strlen($sn1);
//                    for ($i=$length;$i<9;$i++){
//                        $sn2.='0';
//                    }
//
//                    $sn = $num.$sn2.$id;
////                    dump($sn2);
//                    $data['goods_sn'] = $sn;
//                    $res = Db::name('goods')->where('id',$id)->update($data);
//                }else{
//                    $res = Db::name('goods')->where('id',$id)->update($data);
//                }
//                if ($res){
////                    return json(['status'=>1,'msg'=>'商品添加成功']);
//                    $this->success('商品修改成功',url('Admin/goods/index'),1);
//                }else{
////                    return json(['status'=>0,'msg'=>'添加失败了']);
//                    $this->error('商品修改失败',url('Admin/goods/index'),1);
//                }
////
//
//        }
//
//
//    }

    //删除商品
    public function deletegoods(){
        $goodsid = input('goodsid');
        $logic = new GoodsLogic();
        Db::startTrans();
        try{
            $res = Db::name('goods')->where('id',$goodsid)->delete();
            $res1= $logic->delspecattr($goodsid);
            if ($res&&$res1){
                Db::commit();
                return json(['status'=>1,'msg'=>'商品删除成功']);
            }else{
                return json(['status'=>0,'msg'=>'商品删除失败']);
            }
        }catch (\PDOException $e){
            Db::rollback();
        }


//




    }



}