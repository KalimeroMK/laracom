<?php

namespace App\Http\Controllers\Admin\Brands;

use App\Http\Controllers\Controller;
use App\Shop\Brands\Repositories\BrandRepository;
use App\Shop\Brands\Repositories\BrandRepositoryInterface;
use App\Shop\Brands\Requests\CreateBrandRequest;
use App\Shop\Brands\Requests\UpdateBrandRequest;
use Illuminate\Http\Request;

class BrandController extends Controller {

    /**
     * @var BrandRepositoryInterface
     */
    private $brandRepo;

    /**
     * BrandController constructor.
     *
     * @param BrandRepositoryInterface $brandRepository
     */
    public function __construct(BrandRepositoryInterface $brandRepository) {
        $this->brandRepo = $brandRepository;
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index() {
        
        $list = $this->brandRepo->listBrands(['*'], 'name', 'asc')->all();
        
        if (request()->has('q')) {
            $list = $this->brandRepo->searchBrand(request()->input('q'))->all();
        }

        $data = $this->brandRepo->paginateArrayResults($list);
        return view('admin.brands.list', ['brands' => $data]);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create() {
        return view('admin.brands.create');
    }

    /**
     * @param CreateBrandRequest $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(CreateBrandRequest $request) {
        //$this->brandRepo->createBrand($request->all());
        $this->brandRepo->createBrand($request->except('_token', '_method'));
        return redirect()->route('admin.brands.index')->with('message', 'Create brand successful!');
    }

    /**
     * @param $id
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit($id) {
        return view('admin.brands.edit', ['brand' => $this->brandRepo->findBrandById($id)]);
    }

    /**
     * @param UpdateBrandRequest $request
     * @param $id
     *
     * @return \Illuminate\Http\RedirectResponse
     * @throws \App\Shop\Brands\Exceptions\UpdateBrandErrorException
     */
    public function update(UpdateBrandRequest $request, $id) {
        $brand = $this->brandRepo->findBrandById($id);
        $update = new BrandRepository($brand);
        $update->updateBrand($request->except('_token', '_method'));
        
        
        return redirect()->route('admin.brands.edit', $id)->with('message', 'Update successful!');
    }

    /**
     * @param $id
     *
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function destroy($id) {
        $brand = $this->brandRepo->findBrandById($id);
        $brandRepo = new BrandRepository($brand);
        $brandRepo->dissociateProducts();
        $brandRepo->deleteBrand();
        return redirect()->route('admin.brands.index')->with('message', 'Delete successful!');
    }
    
    
    /**
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function removeImage(Request $request) {
        $this->brandRepo->deleteFile($request->only('brand'));
        request()->session()->flash('message', 'Image delete successful');
        return redirect()->route('admin.brands.edit', $request->input('brand'));
    }

}
