<?php

namespace App\Http\Controllers;

use App\Http\Models\Goods;
use Illuminate\Http\Request;
use Response;
use Redirect;

/**
 * 商店控制器
 * Class LoginController
 *
 * @package App\Http\Controllers
 */
class ShopController extends Controller
{
    // 商品列表
    public function goodsList(Request $request)
    {
        $goodsList = Goods::query()->where('is_del', 0)->orderBy('id', 'desc')->paginate(10);
        foreach ($goodsList as $goods) {
            $goods->traffic = flowAutoShow($goods->traffic * 1048576);
        }

        $view['goodsList'] = $goodsList;

        return Response::view('shop/goodsList', $view);
    }

    // 添加商品
    public function addGoods(Request $request)
    {
        if ($request->method() == 'POST') {
            $name = $request->get('name');
            $desc = $request->get('desc', '');
            $traffic = $request->get('traffic');
            $price = $request->get('price', 0);
            $score = $request->get('score', 0);
            $type = $request->get('type', 1);
            $days = $request->get('days', 90);
            $status = $request->get('status');

            if (empty($name) || empty($traffic)) {
                $request->session()->flash('errorMsg', '请填写完整');

                return Redirect::back()->withInput();
            }

            // 套餐必须有价格
            if ($type == 2 && $price <= 0) {
                $request->session()->flash('errorMsg', '套餐价格必须大于0');

                return Redirect::back()->withInput();
            }

            // 套餐有效天数必须大于90天
            if ($type == 2 && $days < 90) {
                $request->session()->flash('errorMsg', '套餐有效天数必须不能少于90天');

                return Redirect::back()->withInput();
            }

            // 流量不能超过1PB
            if ($traffic > 1073741824) {
                $request->session()->flash('errorMsg', '内含流量不能超过1PB');

                return Redirect::back()->withInput();
            }

            // 商品LOGO
            $logo = '';
            if ($request->hasFile('logo')) {
                $file = $request->file('logo');
                $fileType = $file->getClientOriginalExtension();
                $logoName = date('YmdHis') . mt_rand(1000, 2000) . '.' . $fileType;
                $move = $file->move(base_path() . '/public/upload/image/goods/', $logoName);
                $logo = $move ? '/upload/image/goods/' . $logoName : '';
            }

            $obj = new Goods();
            $obj->name = $name;
            $obj->desc = $desc;
            $obj->logo = $logo;
            $obj->traffic = $traffic;
            $obj->price = $price;
            $obj->score = $score;
            $obj->type = $type;
            $obj->days = $days;
            $obj->is_del = 0;
            $obj->status = $status;
            $obj->save();

            if ($obj->id) {
                // 生成SKU
                $obj->sku = 'S0000' . $obj->id;
                $obj->save();

                $request->session()->flash('successMsg', '添加成功');
            } else {
                $request->session()->flash('errorMsg', '添加失败');
            }

            return Redirect::to('shop/addGoods');
        } else {
            return Response::view('shop/addGoods');
        }
    }

    // 编辑商品
    public function editGoods(Request $request)
    {
        $id = $request->get('id');

        if ($request->method() == 'POST') {
            $name = $request->get('name');
            $desc = $request->get('desc');
            $price = $request->get('price', 0);
            $status = $request->get('status');

            $goods = Goods::query()->where('id', $id)->first();
            if (!$goods) {
                $request->session()->flash('errorMsg', '商品不存在');

                return Redirect::back();
            }

            if (empty($name)) {
                $request->session()->flash('errorMsg', '请填写完整');

                return Redirect::back()->withInput();
            }

            // 套餐必须有价格
            if ($goods->type == 2 && $price <= 0) {
                $request->session()->flash('errorMsg', '套餐价格必须大于0');

                return Redirect::back()->withInput();
            }

            // 商品LOGO
            $logo = '';
            if ($request->hasFile('logo')) {
                $file = $request->file('logo');
                $fileType = $file->getClientOriginalExtension();
                $logoName = date('YmdHis') . mt_rand(1000, 2000) . '.' . $fileType;
                $move = $file->move(base_path() . '/public/upload/image/goods/', $logoName);
                $logo = $move ? '/upload/image/goods/' . $logoName : '';
            }

            $data = [
                'name'   => $name,
                'desc'   => $desc,
                'logo'   => $logo,
                'price'  => $price * 100, // 更新时修改器不生效，需要手动*100，原因未知
                'status' => $status
            ];

            $ret = Goods::query()->where('id', $id)->update($data);
            if ($ret) {
                $request->session()->flash('successMsg', '编辑成功');
            } else {
                $request->session()->flash('errorMsg', '编辑失败');
            }

            return Redirect::to('shop/editGoods?id=' . $id);
        } else {
            $view['goods'] = Goods::query()->where('id', $id)->first();

            return Response::view('shop/editGoods', $view);
        }
    }

    // 删除商品
    public function delGoods(Request $request)
    {
        $id = $request->get('id');

        Goods::query()->where('id', $id)->update(['is_del' => 1]);

        return Response::json(['status' => 'success', 'data' => '', 'message' => '删除成功']);
    }
}
