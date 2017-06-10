<?php
/**
 * EmployeeController.class.php
 *
 * @Description: 业务管理模块控制器
 * @Author     : liebert
 * @Date       : 2017/02/22
 */

namespace Admin\Controller;
use Think\Controller;

class EmployeeController extends BaseController {

    /***************************************************************************************
     *                                    员工模块
     ****************************************************************************************/
    /**
     * 工人管理
     */
    public function worker(){
        $this->display();
    }

    /**
     * 获取工人数据集
     */
    public function getWorker(){
        // fetch data and check
        $page     = $_POST['page'];
        $pageSize = $_POST['rows'];
        $name     = isset($_POST['name']) ? trim($_POST['name']) : false;
        $idcard   = isset($_POST['idcard']) ? trim($_POST['idcard']) : false;
        $deliverydateFrom = isset($_POST['deliverydateFrom']) ? strtotime($_POST['deliverydateFrom']) : false;
        $deliverydateTo = isset($_POST['deliverydateTo']) ? strtotime($_POST['deliverydateTo']) : false;
        $isinservice  = $_POST['isinservice'];
        $s        = isset($_POST['sort']) ? trim($_POST['sort']) : 'id';
        $o        = isset($_POST['order']) ? trim($_POST['order']) : 'desc';

        // create and format data we need
        $s  = explode(',',$s);
        $o  = explode(',',$o);
        $sort = array();
        foreach( $s as $key => $value ){
            $sort[$value] = $o[$key];
        }

        $map = array();
        if( $name ){
            $map['name'] = array('like', '%'.$name.'%');
        }
        if( $idcard ){
            $map['idcard'] = array('like', '%'.$idcard.'%');
        }
        if( $deliverydateFrom ){
            $map['deliverydate'][] = array('egt', $deliverydateFrom);
        }
        if( $deliverydateTo ){
            $map['deliverydate'][] = array('elt', $deliverydateTo);
        }
        if( $isinservice ){
            $map['isinservice'] = $isinservice == 'true' ? array('like', 'Y') : array('like', 'N');
        }

        // create Model
        $workerModel = M('EmployeeWorker');
        // sum the number the data
        $total = $workerModel->where($map)->count();
        // query
        $data  = $workerModel->field('commission',true)->where($map)->order($sort)->page($page, $pageSize)->select();

        // format result we need
        $data = array_map(function($value){
            $value['deliverydate'] = $value['deliverydate'] ? date('Y-m-d',$value['deliverydate']) : '';
            $value['terminationdate'] = $value['terminationdate'] ? date('Y-m-d',$value['terminationdate']) : '';
            return $value;
        }, $data);

        // response
        if( IS_AJAX ) {
            $this->ajaxReturn( array(
                "total" => $total,
                "rows"  => $data
            ) );
        } else {
            return array(
                "total" => $total,
                "rows"  => $data
            );
        }
    }

    /**
     * batchProcessWorker
     */
    public function batchProcessWorker(){
        $data  = $_POST;
        $workerModel = D('EmployeeWorker');

        // updated
        if ( $source = json_decode($data['updated']) ) {
            foreach( $source as $key => $vo ){
                $data = array();
                foreach( $vo as $k=>$d ){
                    $data[$k] = $d;
                }

                if( !$workerModel->create($data) ){

                    $this->ajaxReturn(array(
                        "state" => 0,
                        "msg"   => $workerModel->getError()
                    ));
                }

                $error = $workerModel->save();

                if( $error === false ){

                    $this->ajaxReturn(array(
                        "state" => 0,
                        "msg"   => "更新失败"
                    ));
                }

                // 记录到日志中
                $log = $this->user['name'].'更新员工基础信息，员工ID：【'.$data['id'].'】，员工姓名：【'.$data['name'].'】';
                saveLog(0,$log, $this->user['id'], $this->user['name']);
            }
        }// end update


        // add
        if ( $source = json_decode($data['inserted']) ) {
            foreach( $source as $key=>$vo ){
                $data = array();
                foreach( $vo as $k=>$d ){
                    $data[$k] = $d;
                }

                if( !$workerModel->create($data) ){

                    $this->ajaxReturn(array(
                        "state" => 0,
                        "msg"   => $workerModel->getError()
                    ));
                }

                $error = $workerModel->add( $data );

                if( $error === false ){

                    $this->ajaxReturn(array(
                        "state" => 0,
                        "msg"   => "添加失败"
                    ));
                }

                // 记录到日志中
                $log = $this->user['name'].'增加员工基础信息，员工ID：【'.$error.'】，员工姓名：【'.$data['name'].'】';
                saveLog(0,$log, $this->user['id'], $this->user['name']);

            }// end foreach

        }//end add


        // deleted
        if ( $source = json_decode($data['deleted']) ) {
            $data = array();
            foreach( $source as $key=>$vo ) {
                $data[] = $vo->id;
            }
            $data = implode(',', $data);
            $error = $workerModel->delete($data);
            if( $error === false ){

                $this->ajaxReturn(array(
                    "state" => 0,
                    "msg"   => "删除失败"
                ));
            }

            // 记录到日志中
            $log = $this->user['name'].'删除员工基础信息，员工ID：【'.$data['id'].'】，员工姓名：【'.$data['name'].'】';
            saveLog(0,$log, $this->user['id'], $this->user['name']);
        }

        // response
        $this->ajaxReturn(array(
            "state" => 1,
            "msg"   => "保存成功"
        ));
    }

    /**
     * 获取工人列表
     */
    public function getWorkerList(){
        $page     = $_POST['page'];
        $pageSize = $_POST['rows'];

        if( isset($_POST['q']) ){
            $map['name'] = array('like', '%'.$_POST['q'].'%');
        }

        $total = M('employee_worker')->where($map)->count();
        $data  = M('employee_worker')->field('id,name,idcard')->where($map)->page($page, $pageSize)->order('name asc')->select();

        if( IS_AJAX ) {
            $this->ajaxReturn(array(
                'total' => $total,
                'rows'  => $data
            ));
        } else {
            return array(
                'total' => $total,
                'rows'  => $data
            );
        }
    }
    /**
     * 获取工人列表树
     */
    public function getWorkerTree(){

        $tree = M('employee_worker')->field('id,name,originid')->select();

        $tree = array_map( function($value){
            $value['text'] = $value['name'];
            $value['icon'] = 'icon-user';
            return $value;
        }, $tree );
        $tree = listToTree($tree, 'id', 'originid', 'children', '0' );
        if( IS_AJAX ) {
            $this->ajaxReturn($tree);
        } else {
            return $tree;
        }
    }

    /***************************************************************************************
     *                                   工头信息管理
     ****************************************************************************************/
    /**
     * 工头管理
     */
    public function workerExtend(){
        $this->display('worker-extend');
    }

    /**
     * 获取工人数据集
     */
    public function getWorkerExtend(){
        // fetch data and check
        $page     = $_POST['page'];
        $pageSize = $_POST['rows'];
        $name     = isset($_POST['name']) ? trim($_POST['name']) : false;
        $idcard   = isset($_POST['idcard']) ? trim($_POST['idcard']) : false;
        $deliverydateFrom = isset($_POST['deliverydateFrom']) ? strtotime($_POST['deliverydateFrom']) : false;
        $deliverydateTo = isset($_POST['deliverydateTo']) ? strtotime($_POST['deliverydateTo']) : false;
        $isinservice  = $_POST['isinservice'];
        $s        = isset($_POST['sort']) ? trim($_POST['sort']) : 'id';
        $o        = isset($_POST['order']) ? trim($_POST['order']) : 'desc';

        // create and format data we need
        $s  = explode(',',$s);
        $o  = explode(',',$o);
        $sort = array();
        foreach( $s as $key => $value ){
            $sort[$value] = $o[$key];
        }

        $map = array();
        if( $name ){
            $map['name'] = array('like', '%'.$name.'%');
        }
        if( $idcard ){
            $map['idcard'] = array('like', '%'.$idcard.'%');
        }
        if( $deliverydateFrom ){
            $map['deliverydate'][] = array('egt', $deliverydateFrom);
        }
        if( $deliverydateTo ){
            $map['deliverydate'][] = array('elt', $deliverydateTo);
        }
        if( $isinservice ){
            $map['isinservice'] = $isinservice == 'true' ? array('like', 'Y') : array('like', 'N');
        }

        // create Model
        $workerModel = M('EmployeeWorker');
        // sum the number the data
        $total = $workerModel->where($map)->count();
        // query
        $data  = $workerModel->field('id,name,categoryid,commission')->where($map)->order($sort)->page($page, $pageSize)->select();

        // format result we need
        $data = array_map(function($value){
            $value['deliverydate'] = $value['deliverydate'] ? date('Y-m-d',$value['deliverydate']) : '';
            $value['terminationdate'] = $value['terminationdate'] ? date('Y-m-d',$value['terminationdate']) : '';
            return $value;
        }, $data);

        // response
        if( IS_AJAX ) {
            $this->ajaxReturn( array(
                "total" => $total,
                "rows"  => $data
            ) );
        } else {
            return array(
                "total" => $total,
                "rows"  => $data
            );
        }
    }

    /**
     * batchProcessWorkerExtend
     */
    public function batchProcessWorkerExtend(){
        $data  = $_POST;
        $workerModel = M('EmployeeWorker');

        // updated
        if ( $source = json_decode($data['updated']) ) {
            foreach( $source as $key => $vo ){
                $data = array();
                foreach( $vo as $k=>$d ){
                    $data[$k] = $d;
                }

                $error = $workerModel->data($data)->save();

                if( $error === false ){

                    $this->ajaxReturn(array(
                        "state" => 0,
                        "msg"   => "保存失败"
                    ));
                }

                // 记录到日志中
                $log = $this->user['name'].'更新员工扩展信息，员工ID：【'.$data['id'].'】，员工姓名：【'.$data['name'].'】';
                saveLog(0,$log, $this->user['id'], $this->user['name']);
            }
        }// end update

        // response
        $this->ajaxReturn(array(
            "state" => 1,
            "msg"   => "保存成功"
        ));
    }


    /***************************************************************************************
     *                                    工时表单
     ****************************************************************************************/
    /**
     * 工时表管理
     */
    public function worktime(){
        $this->display();
    }
    /**
     * 工时表数据管理
     */
    public function getWorktime(){

        // fetch data and check
        $page     = $_POST['page'];
        $pageSize = $_POST['rows'];
        $name     = isset($_POST['name']) ? trim($_POST['name']) : false;
        $idcard   = isset($_POST['idcard']) ? trim($_POST['idcard']) : false;
        $timeF    = isset($_POST['timef']) ? strtotime($_POST['timef']) : false;
        $timeT    = isset($_POST['timet']) ? strtotime($_POST['timet']) : false;
        $s        = isset($_POST['sort']) ? trim($_POST['sort']) : 'id';
        $o        = isset($_POST['order']) ? trim($_POST['order']) : 'desc';

        // create and format data we need
        $s  = explode(',',$s);
        $o  = explode(',',$o);
        $sort = array();
        foreach( $s as $key => $value ){
            $sort[$value] = $o[$key];
        }

        $map = array();
        if( $name ){
            $map['name'] = array('like', '%'.$name.'%');
        }
        if( $idcard ){
            $map['idcard'] = array('like', '%'.$idcard.'%');
        }
        if( $timeF ){
            $map['time'][] = array('egt', $timeF);
        }
        if( $timeT ){
            $map['time'][] = array('elt', $timeT);
        }

        // create Model
        $worketimeModel = D('EmployeeWorktimeView');
        // sum the number the data
        $total = $worketimeModel->where($map)->count();
        // query
        $data  = $worketimeModel->where($map)->order($sort)->page($page, $pageSize)->select();

        // format data
        $data = array_map( function($value){
            $value['time']        = $value['time'] ? date('Y-m-d', $value['time']) : '';
            $value['checkindate'] = $value['checkindate'] ? date('Y-m-d', $value['checkindate']) : '';
            $value['leavedate']   = $value['leavedate'] ? date('Y-m-d', $value['leavedate']) : '';
            return $value;
        }, $data );

        // response
        if( IS_AJAX ) {
            $this->ajaxReturn( array(
                "total" => $total,
                "rows"  => $data
            ) );
        } else {
            return array(
                "total" => $total,
                "rows"  => $data
            );
        }

    }

    /**
     * batchProcessWorketime
     */
    public function batchProcessWorketime(){
        $data  = $_POST;
        $worktimeModel = D('EmployeeWorktime');

        // updated
        if ( isset($data['updated']) && $source = json_decode($data['updated']) ) {
            foreach( $source as $key => $vo ){
                $data = array();
                foreach( $vo as $k=>$d ){
                    $data[$k] = $d;
                }

                if( !$worktimeModel->create($data,2) ){

                    $this->ajaxReturn(array(
                        "state" => 0,
                        "msg"   => $worktimeModel->getError()
                    ));
                }
                // time
                $error = $worktimeModel->save();

                if( $error === false ){

                    $this->ajaxReturn(array(
                        "state" => 0,
                        "msg"   => "更新失败"
                    ));
                }

                // 记录到日志中
                $log = $this->user['name'].'更新工时表单数据，ID：【'.$data['id'].'】';
                saveLog(0,$log, $this->user['id'], $this->user['name']);
            }
        }// end update


        // add
        if ( isset($data['inserted']) && $source = json_decode($data['inserted']) ) {
            foreach( $source as $key=>$vo ){
                $data = array();
                foreach( $vo as $k=>$d ){
                    $data[$k] = $d;
                }
                $data['time'] = strtotime( $data['time'] );
                if( !$worktimeModel->create($data,1) ){

                    $this->ajaxReturn(array(
                        "state" => 0,
                        "msg"   => $worktimeModel->getError()
                    ));
                }


                $error = $worktimeModel->add( $data );

                if( $error === false ){
                    $this->ajaxReturn(array(
                        "state" => 0,
                        "msg"   => "添加失败"
                    ));
                }

                // 记录到日志中
                $log = $this->user['name'].'增加工时表单数据，ID：【'.$error.'】';
                saveLog(0,$log, $this->user['id'], $this->user['name']);

            }// end foreach

        }//end add


        // deleted
        if ( isset($data['deleted']) && $source = json_decode($data['deleted']) ) {
            $data = array();
            foreach( $source as $key=>$vo ) {
                $data[] = $vo->id;
            }
            $data = implode(',', $data);
            $error = $worktimeModel->delete($data);
            if( $error === false ){

                $this->ajaxReturn(array(
                    "state" => 0,
                    "msg"   => "删除失败"
                ));
            }

            // 记录到日志中
            $log = $this->user['name'].'删除工时表单数据，ID：【'.$data['id'].'】';
            saveLog(0,$log, $this->user['id'], $this->user['name']);
        }

        //更新工头提成数据

        // response
        $this->ajaxReturn(array(
            "state" => 1,
            "msg"   => "保存成功"
        ));
    }

    /***************************************************************************************
     *                                    汇总表单
     ****************************************************************************************/
    public function workSum(){
        $this->display('work-sum');
    }


    /**
     * 获取总表单数据
     * @description: 按照工头 分组统计
     *
     */
    public function getWorkSum(){
        // fetch data and check
        $page     = $_POST['page'];
        $pageSize = $_POST['rows'];
        $name     = isset($_POST['name']) ? trim($_POST['name']) : false;
        $idcard   = isset($_POST['idcard']) ? trim($_POST['idcard']) : false;
        $timeF    = isset($_POST['timef']) ? strtotime($_POST['timef']) : false;
        $timeT    = isset($_POST['timet']) ? strtotime($_POST['timet']) : false;
        $s        = isset($_POST['sort']) ? trim($_POST['sort']) : 'id';
        $o        = isset($_POST['order']) ? trim($_POST['order']) : 'desc';

        // create and format data we need
        $s  = explode(',',$s);
        $o  = explode(',',$o);
        $sort = array();
        foreach( $s as $key => $value ){
            $sort[$value] = $o[$key];
        }

        // create one instance of WorkSumViewModel
        $sumModel = D('WorkSumView');

        // get where caluse
        $map = array();
        if( $name ){
            $map['name'] = array('like', '%'.$name.'%');
        }
        if( $idcard ){
            $map['idcard'] = array('like', '%'.$idcard.'%');
        }
        if( $timeF ){
            $map['time'][] = array('egt', $timeF);
        }
        if( $timeT ){
            $map['time'][] = array('elt', $timeT);
        }

        // sum the number the data
        $total = $sumModel->where($map)->count();

        // query
        $data  = $sumModel->where($map)->order($sort)->page($page, $pageSize)->select();

        // format result we need
        foreach( $data as $key=>$value ){

            // 员工-工时工资
            if($data[$key]['isagreement'] == 'Y'){
                $data[$key]['isagreement'] = '是';
                $data[$key]['worktime_wages']   = round($data[$key]['fullagreementwage'] * $data[$key]['totaltime'], 2);
            } else {
                $data[$key]['isagreement'] = '否';
                $data[$key]['worktime_wages']   = round($data[$key]['undercontractwage'] * $data[$key]['totaltime'], 2);
            }

            // 员工-工头提成工资
            $data[$key]['commission_wages'] = ($data[$key]['categoryid'] > 1) ? $this->getCommissionWages($data[$key]['workerid'],$data[$key]['time']) : '0.00';

            // 员工-扣除工资
            $data[$key]['deductions']       = $data[$key]['waterandelectricity'] + $data[$key]['overallcost'] + $data[$key]['iccard'] + $data[$key]['penalty'] + $data[$key]['commercialinsurance'] + $data[$key]['managementcost'] + $data[$key]['carfare'] + $data[$key]['borrow'] + $data[$key]['liquidateddamages'];

            // 员工-实发工资
            $data[$key]['real_wages']       = $data[$key]['worktime_wages'] + $data[$key]['commission_wages'] - $data[$key]['deductions'];

            // 公司-工时收入
            $data[$key]['worktime_income'] = round($data[$key]['price'] * $data[$key]['totaltime'], 2);

            // 公司-净利润
            $data[$key]['real_income'] = $data[$key]['worktime_income'] - $data[$key]['real_wages'];

            $data[$key]['time']            = $data[$key]['time'] ? date('Y-m-d',$data[$key]['time']) : '';
            $data[$key]['checkindate']     = $data[$key]['checkindate'] ? date('Y-m-d',$data[$key]['checkindate']) : '';
            $data[$key]['leavedate']       = $data[$key]['leavedate'] ? date('Y-m-d',$data[$key]['leavedate']) : '';
            $data[$key]['deliverydate']    = $data[$key]['deliverydate'] ? date('Y-m-d',$data[$key]['deliverydate']) : '';
        }

        // response
        if( IS_AJAX ) {
            $this->ajaxReturn( array(
                "total" => $total,
                "rows"  => $data
            ) );
        } else {
            return array(
                "total" => $total,
                "rows"  => $data
            );
        }
    }

    /**
     * getCommissionWages
     * @description 计算工头提成工资
     * @param integer $workerid 工头id
     * @return float
     */
    private function getCommissionWages( $workerid, $time ){
        $sumWages = 0.00;

        $map = array();
        if( $workerid && $time ){

            $arr = getdate($time);

            $timeStart = strtotime($arr['year'].'-'.$arr['mon']);
            $timeEnd   = strtotime($arr['year'].'-'.($arr['mon']+1));

            $map['originid'] = array('eq', $workerid);
            $map['time'][]   = array('egt', $timeStart);
            $map['time'][]   = array('elt', $timeEnd);

            // 查询此工头的工人
            $workers = M('EmployeeWorktime')->field('isagreement,undercontractwage,fullagreementwage,totaltime,commission,originid')->join('LEFT JOIN __EMPLOYEE_WORKER__ ON __EMPLOYEE_WORKTIME__.workerid = __EMPLOYEE_WORKER__.id')->where($map)->select();

            foreach( $workers as $key=>$value ){
                if($value['isagreement'] == 'Y'){
                    $sumWages += round($value['totaltime'] * $value['fullagreementwage'] * $value['commission'], 2);
                } else {
                    $sumWages += round($value['totaltime'] * $value['undercontractwage'] * $value['commission'], 2);
                }
            }
        }

        return $sumWages;
    }

}