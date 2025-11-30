<?php

namespace app\api\controller\v1;

class Test extends Base {


    public function getIndex(){

        $data = [
            'list' => 'a'
        ];
//
//        return $this->error(1);
        return $this->success($data);
    }

    public function getRechargeagents(){
        $where = ['user_id' => 24];

        model('User2')->reward($where, 1000);
        return $this->success();
    }



}