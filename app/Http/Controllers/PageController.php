<?php

namespace App\Http\Controllers;
use Session;
use App\Slide;
use App\Product;
use App\ProductType;
use App\Cart;
use App\Bill;
use App\BillDetail;
use App\Customer;
use App\User;
use Hash;//ma hoa mat khau
use Auth;

use Illuminate\Http\Request;
use NunoMaduro\Collision\Adapters\Phpunit\Style;

class PageController extends Controller
{

    public function getIndex(){
        $slide = Slide::all();
    	//return view('page.trangchu',['slide'=>$slide]);
        $new_product = Product::where('new',1)->paginate(8);
        //dd($new_product);
        $sanpham_khuyenmai = Product::where('promotion_price','<>',0)->paginate(4);

    	return view('page.trangchu',compact('slide','new_product','sanpham_khuyenmai'));
    }




    public function getLoaiSp($type){

        //lay san pham hien thi theo loai
        $sp_theoloai=Product::where('id_type',$type)->limit(3)->get();

        //lay san pham hien thi khac <>loai
        $sp_khac=Product::where('id_type','<>',$type)->limit(3)->get();

        //lay san pham hien thi theo loai typeproduct cho menu ben trai
        $loai=ProductType::all();

        //Lay ten Loai san pham moi khi chung ta chon danh muc loai san pham(phan menu ben trai)
        $loai_sp=ProductType::where('id',$type)->first();
    	return view('page.loai_sanpham',compact('sp_theoloai','sp_khac','loai','loai_sp'));


}
    public function getChitiet(Request $req){
        //lay san pham chi tiet , khi click chuot vao 1 sp thi no cho chi tiet rieng san pham dó theo ìd
        $sanpham=Product::where('id',$req->id)->first();

        //Lay san pham lien quan = la san phâm tương tự
        $sp_tuongtu=Product::where('id_type',$sanpham->id_type)->paginate(3);
        // return view('page.chitiet_sanpham',compact('sanpham','sp_tuongtu'));

        //lay san pham ban chay = la sn pham co truong new=1
        $sp_banchay= Product::where('promotion_price','<>',0)->paginate(3);

        //Lay san pham moi nhat la san pham moi cap nhat
        $sp_new= Product::where('new',1)->paginate(4);

        // $sp_moi= Product::select('id','name','id_type','description','unit_price','promotion_price','image','unit','new','created_at','updated_at')->where('new','>',0)->orderBy('updated_at','ASC')->paginate(3);
        return view('page.chitiet_sanpham',compact('sp_new','sanpham','sp_tuongtu','sp_banchay'));
        }
    public function getLienhe(){
        return view('page.lienhe');
        }
    public function getAbout(){
        return view('page.about');
        }

    public function getAddToCart(Request $req, $id){
        $product = Product::find($id);
        $oldCart = Session('cart')?Session::get('cart'):null;
        $cart = new Cart($oldCart);
        $cart->add($product,$id);
        $req->session()->put('cart', $cart);
        return redirect()->back();
    }



    public function getDelItemCart($id){
        $oldCart=Session::has('cart')?Session::get('cart'):null;
        $cart= new Cart($oldCart);
        $cart->removeItem($id);//xoa nhieu-oModel Cart
        Session::put('cart',$cart);
        return redirect()->back();
    }

    public function getCheckout(){
        $oldCart = Session::get('cart');
        $cart = new Cart($oldCart);
        return view('page.dathang')->with(['cart'=>Session::get('cart'), 'product_cart'=>$cart->items,'totalPrice'=>$cart->totalPrice,'totalQty'=>$cart->totalQty]);
    }
    public function postCheckout(Request $req){
        $cart = Session::get('cart');

        $customer = new Customer;
        $customer->name = $req->full_name;
        $customer->gender = $req->gender;
        $customer->email = $req->email;
        $customer->address = $req->address;
        $customer->phone_number = $req->phone;
        $customer->note = $req->notes;
        $customer->save();

        $bill = new Bill;
        $bill->id_customer = $customer->id;
        $bill->date_order = date('Y-m-d');
        $bill->total = $cart->totalPrice;
        $bill->payment = $req->payment_method;
        $bill->note = $req->notes;
        $bill->save();

        foreach($cart->items as $key=>$value){
            $bill_detail = new BillDetail;
            $bill_detail->id_bill = $bill->id;
            $bill_detail->id_product = $key;//$value['item']['id'];
            $bill_detail->quantity = $value['qty'];
            $bill_detail->unit_price = $value['price']/$value['qty'];
            $bill_detail->save();
        }

        Session::forget('cart');
        return redirect('indexs')->with('thongbao','Đặt hàng thành công');
    }
    public function getLogin(){
        return view('page.dangnhap');
    }
    public function getSignin(){
        return view('page.dangki');
    }

    public function postSignin(Request $req){
        $this->validate($req,
            [
                'email'=>'required|email|unique:users,email',
                'password'=>'required|min:6|max:20',
                'fullname'=>'required',
                're_password'=>'required|same:password'
            ],
            [
                'email.required'=>'Vui lòng nhập email',
                'email.email'=>'Không đúng định dạng email',
                'email.unique'=>'Email đã có người sử dụng',
                'password.required'=>'Vui lòng nhập mật khẩu',
                're_password.same'=>'Mật khẩu không giống nhau',
                'password.min'=>'Mật khẩu ít nhất 6 kí tự'
            ]);
        $user = new User();
        $user->full_name = $req->fullname;
        $user->email = $req->email;
        $user->password = Hash::make($req->password);
        $user->phone = $req->phone;
        $user->address = $req->address;
        $user->save();

        return redirect()->back()->with('thanhcong','Tạo tài khoản thành công');
    }

    public function postLogin(Request $req){
        $this->validate($req,
            [
                'email'=>'required|email',
                'password'=>'required|min:6|max:20'
            ],
            [
                'email.required'=>'Vui lòng nhập email',
                'email.email'=>'Email không đúng định dạng',
                'password.required'=>'Vui lòng nhập mật khẩu',
                'password.min'=>'Mật khẩu ít nhất 6 kí tự',
                'password.max'=>'Mật khẩu không quá 20 kí tự'
            ]
        );
        $credentials = array('email'=>$req->email,'password'=>$req->password);
        $user = User::where([
                ['email','=',$req->email],
                ['password','=',$req->password]
            ]);

        if($user){
            if(Auth::attempt($credentials)){
         Session::put('checkLogin','true');
            return redirect('indexs')->with(['flag'=>'success','message'=>'Đăng nhập thành công']);
            }
            else{
                return redirect()->back()->with(['flag'=>'danger','message'=>'Đăng nhập không thành công']);

            }
        }
        else{
           return redirect()->back()->with(['flag'=>'danger','message'=>'Tài khoản chưa kích hoạt']);
        }

    }
    public function postLogout(){
        Auth::logout();
        return redirect()->route('trang-chu');
    }



}
