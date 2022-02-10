<?php

namespace Modules\Page\Http\Controllers;

use App\Classes\FileDimensionHelper;
use App\Classes\FileHelper;
use App\Classes\LanguageHelper;
use App\Models\Catalogs\MainCatalog;
use App\Models\ContentPage;
use App\Models\ContentPageTranslation;
use App\Models\File;
use App\Models\FileDimension;
use App\Models\Gallery;
use App\Models\Language;
use App\Models\Navigation;
use App\Models\OtherSetting;
use App\Models\Product;
use App\Models\Tour;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Page\Models\Page;

class PageController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        $navigations = Menu::where('module', 'Page')->orderBy('position', 'asc')->with('translations')->get();

        return view('page::admin.navigations', compact('navigations'));
    }

    public function pages($navigationId)
    {
        $navigation = Menu::getMenuPageModule($navigationId);
        if (is_null($navigation)) {
            return redirect()->route('page.list')->withErrors(['administration_messages.navigation_not_found']);
        }

        $navigations = Menu::getAllMenusPageModule();
        $pages       = $navigation->content_pages()->orderBy('content_pages.position', 'asc')->with('translations')->get();
        $pages->map(function ($page) {
            $page->in_gallery = $page->galleries()->where('show_in_gallery', 1)->count();
            $page->in_header  = $page->galleries()->where('show_in_header', 1)->count();

            return $page;
        });
        $otherSettings = OtherSetting::first();

        $galleryContentPageTypeId = Gallery::$CONTENT_PAGE_TYPE_ID;

        return view('admin.content_pages.pages', compact('navigation', 'navigations', 'pages', 'otherSettings', 'galleryContentPageTypeId'));

    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('page::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        return view('page::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        return view('page::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        //
    }




    public function contentPages($navigationId)
    {
        $navigation = Navigation::getNavigationThroughModules($navigationId);
        if (is_null($navigation)) {
            return redirect('admin/contents')->withErrors(['administration_messages.navigation_not_found']);
        }

        $navigations = Navigation::getAllNavigationsThroughModules($navigationId);
        $pages       = $navigation->content_pages()->orderBy('content_pages.position', 'asc')->with('translations')->get();
        $pages->map(function ($page) {
            $page->in_gallery = $page->galleries()->where('show_in_gallery', 1)->count();
            $page->in_header  = $page->galleries()->where('show_in_header', 1)->count();

            return $page;
        });
        $otherSettings = OtherSetting::first();

        $galleryContentPageTypeId = Gallery::$CONTENT_PAGE_TYPE_ID;

        return view('admin.content_pages.pages', compact('navigation', 'navigations', 'pages', 'otherSettings', 'galleryContentPageTypeId'));
    }

    public function create($navigationId)
    {
        $navigation = Navigation::getNavigationThroughModules($navigationId);
        if (is_null($navigation)) {
            return redirect('admin/contents')->withErrors(['administration_messages.navigation_not_found']);
        }

        $navigations       = Navigation::getAllNavigationsThroughModules($navigationId);
        $languages         = Language::where('active', true)->get();
        $defaultLanguage   = Language::where('code', env('DEF_LANG_CODE'))->first();
        $pages             = $navigation->content_pages()->orderBy('content_pages.position', 'asc')->get();
        $filesPath         = File::getFolderPath();
        $filesPathUrl      = url(File::$MAIN_PATH);
        $files             = FileHelper::getFilesFromDirectory($filesPath);
        $fileRulesInfo     = FileDimensionHelper::getUserInfoMessage(FileDimension::$CONTENT_BOX);
        $fileRulesInfoBox2 = FileDimensionHelper::getUserInfoMessage(FileDimension::$CONTENT_BOX_SECOUND);
        $fileRulesInfoBox3 = FileDimensionHelper::getUserInfoMessage(FileDimension::$CONTENT_BOX_THIRD);
        $requiredDate      = ($navigation->vizualization_type_id == Navigation::$KARETA_SECOND_FILTER_VISUALISATION) ? 'required' : '';
        $otherSettings     = OtherSetting::first();
        $products          = Product::where('active', true)->orderBy('position', 'asc')->with('translations')->get();
        $mainCatalogs      = MainCatalog::where('active', true)->with('translations')->get();

        return view('admin.content_pages.create', compact('navigationId', 'navigations', 'languages', 'defaultLanguage', 'pages', 'files', 'filesPath', 'filesPathUrl', 'fileRulesInfo', 'fileRulesInfoBox2', 'fileRulesInfoBox3', 'requiredDate', 'otherSettings', 'products', 'mainCatalogs'));
    }

    public function store($navigationId, Request $request)
    {
        $navigation = Navigation::getNavigationThroughModules($navigationId);
        if (is_null($navigation)) {
            return redirect('admin/contents')->withErrors(['administration_messages.navigation_not_found']);
        }

        $trim_if_string = function ($var) {
            return is_string($var) ? trim($var) : $var;
        };
        $request->merge(array_map($trim_if_string, $request->all()));

        if (!$request->has('navigation_id') || is_null($request->navigation_id)) {
            return redirect()->back()->withInput()->withErrors(['navigation_id' => ['administration_messages.page_required']]);
        }
        if ($navigation->vizualization_type_id == Navigation::$KARETA_SECOND_FILTER_VISUALISATION) {
            if ($request->has('one_day_event') && $request->one_day_event == "on" && (is_null($request->one_day_event_date) || $request->one_day_event_date == "")) {
                return redirect()->back()->withInput()->withErrors(['navigation_id' => ['administration_messages.one_day_event_date_required']]);
            }

            if (!$request->has('one_day_event') && (is_null($request->from_date) || is_null($request->to_date))) {
                return redirect()->back()->withInput()->withErrors(['navigation_id' => ['administration_messages.period_required']]);
            }
        }
        $navigation = Navigation::getNavigationThroughModules($request->navigation_id);
        if (is_null($navigation)) {
            return redirect()->back()->withInput()->withErrors(['navigation_id' => ['administration_messages.page_invalid']]);
        }

        $languages = LanguageHelper::getActiveLanguages();
        $errors    = Page::getCreateInputErrors($languages, $request);
        if (count($errors) > 0) {
            return redirect()->back()->withInput()->withErrors($errors);
        }

        //One dimmension active
        if ($request->has('content_pages_one_dimensions') && $request->content_pages_one_dimensions == 1) {
            if ($request->hasFile('image')) {
                $request->validate(['image' => FileDimensionHelper::getRules(FileDimension::$CONTENT_BOX)], FileDimensionHelper::messages(FileDimension::$CONTENT_BOX));
                $request['filename']      = pathinfo($request->image->getClientOriginalName(), PATHINFO_FILENAME) . '.png';
                $pieces                   = explode(".", $request->image->getClientOriginalName());
                $name                     = $pieces[0] . '_1.' . $pieces[1];
                $request['filename_box2'] = pathinfo($request->image->getClientOriginalName(), PATHINFO_FILENAME) . '_1.png';
            }
        } else {
            if ($request->hasFile('image')) {
                $request->validate(['image' => FileDimensionHelper::getRules(FileDimension::$CONTENT_BOX)], FileDimensionHelper::messages(FileDimension::$CONTENT_BOX));
                $request['filename'] = pathinfo($request->image->getClientOriginalName(), PATHINFO_FILENAME) . '.png';
            }

            if ($request->hasFile('image_box2')) {
                $pieces = explode(".", $request->image_box2->getClientOriginalName());
                $name   = $pieces[0] . '_1.' . $pieces[1];
                $request->validate(['image_box2' => FileDimensionHelper::getRules(FileDimension::$CONTENT_BOX_SECOUND)], FileDimensionHelper::messages(FileDimension::$CONTENT_BOX_SECOUND));
                $request['filename_box2'] = pathinfo($name, PATHINFO_FILENAME) . '.png';
            }

            if ($request->hasFile('image_box3')) {
                $pieces = explode(".", $request->image_box3->getClientOriginalName());
                $name   = $pieces[0] . '_2.' . $pieces[1];
                $request->validate(['image_box3' => FileDimensionHelper::getRules(FileDimension::$CONTENT_BOX_THIRD)], FileDimensionHelper::messages(FileDimension::$CONTENT_BOX_THIRD));
                $request['filename_box3'] = pathinfo($name, PATHINFO_FILENAME) . '.png';
            }
        }

        $request['position'] = Page::generatePosition($navigation, $request);
        $contentPage         = Page::create(Page::getCreateData($request));
        foreach ($languages as $language) {
            $contentPage->translations()->create(ContentPageTranslation::getCreateData($language, $request));
            if ($language->id == 1) {
                $tour = Tour::create(Tour::getCreateData($contentPage->id, $language, $request));
                $tour->generateFolder();
            }
        }

        Page::validateVisibility($contentPage, false);
        Navigation::validateVisibility($navigation, false);

        //One dimmension active
        if ($request->has('content_pages_one_dimensions') && $request->content_pages_one_dimensions == 1) {
            if ($request->hasFile('image')) {
                $contentPage->saveImage($request->file('image'));
                $contentPage->copyFile($request['filename'], $request['filename_box2']);
            }
        } else {
            if ($request->hasFile('image')) {
                $contentPage->saveImage($request->file('image'));
            }

            if ($request->hasFile('image_box2')) {
                $contentPage->saveImage2($request->file('image_box2'), $request['filename_box2']);
            }

            if ($request->hasFile('image_box3')) {
                $contentPage->saveImage3($request->file('image_box3'), $request['filename_box3']);
            }
        }

        Page::generateToAdBox($contentPage, $languages);
        //        SitemapHelper::generateSiteMap();

        if ($request->has('submitaddnew')) {
            return redirect()->back()->with('success-message', 'administration_messages.successful_create');
        }

        return redirect('admin/contents/loadContentPages/' . $contentPage->navigation_id)->with('success-message', 'administration_messages.successful_create');
    }

    public function positions($navigationId)
    {
        $navigation = Navigation::getNavigationThroughModules($navigationId);
        if (is_null($navigation)) {
            return "";
        }

        $pages           = $navigation->content_pages()->orderBy('content_pages.position', 'asc')->get();
        $defaultLanguage = Language::where('code', env('DEF_LANG_CODE'))->first();

        return view('admin.partials.content_page_positions', compact('pages', 'defaultLanguage'))->render();
    }


    public function edit($id)
    {
        $contentPage = Page::find($id);
        if (is_null($contentPage)) {
            return redirect('admin/contents')->withErrors(['administration_messages.page_not_found']);
        }

        $navigations       = Navigation::where('module_id', Navigation::$CONTENT_MODULE_ID)->orderBy('position', 'asc')->with('translations')->get();
        $languages         = Language::where('active', true)->get();
        $defaultLanguage   = Language::where('code', env('DEF_LANG_CODE'))->first();
        $pages             = $contentPage->navigation->content_pages()->orderBy('content_pages.position', 'asc')->get();
        $filesPath         = File::getFolderPath();
        $filesPathUrl      = url(File::$MAIN_PATH);
        $files             = FileHelper::getFilesFromDirectory($filesPath);
        $fileRulesInfo     = FileDimensionHelper::getUserInfoMessage(FileDimension::$CONTENT_BOX);
        $fileRulesInfoBox2 = FileDimensionHelper::getUserInfoMessage(FileDimension::$CONTENT_BOX_SECOUND);
        $fileRulesInfoBox3 = FileDimensionHelper::getUserInfoMessage(FileDimension::$CONTENT_BOX_THIRD);
        $requiredDate      = ($contentPage->navigation->vizualization_type_id == Navigation::$KARETA_SECOND_FILTER_VISUALISATION) ? 'required' : '';
        $otherSettings     = OtherSetting::first();
        $navigation        = $contentPage->navigation;
        $products          = Product::where('active', true)->orderBy('position', 'asc')->with('translations')->get();
        $mainCatalogs      = MainCatalog::where('active', true)->with('translations')->get();

        return view('admin.content_pages.edit', compact('contentPage', 'languages', 'defaultLanguage', 'pages', 'navigations', 'fileRulesInfo', 'fileRulesInfoBox2', 'fileRulesInfoBox3', 'files', 'filesPath', 'filesPathUrl', 'requiredDate', 'otherSettings', 'navigation', 'products', 'mainCatalogs'));
    }


    public function update($id, Request $request)
    {
        $trim_if_string = function ($var) {
            return is_string($var) ? trim($var) : $var;
        };
        $request->merge(array_map($trim_if_string, $request->all()));

        $contentPage = Page::find($id);
        if (is_null($contentPage)) {
            return redirect('admin/contents')->withErrors(['administration_messages.page_not_found']);
        }

        if (!$request->has('navigation_id') || is_null($request->navigation_id)) {
            return redirect()->back()->withInput()->withErrors(['navigation_id' => ['administration_messages.page_required']]);
        }

        if ($contentPage->navigation->vizualization_type_id == Navigation::$KARETA_SECOND_FILTER_VISUALISATION) {
            if ($request->has('one_day_event') && $request->one_day_event == "on" && (is_null($request->one_day_event_date) || $request->one_day_event_date == "")) {
                return redirect()->back()->withInput()->withErrors(['navigation_id' => ['administration_messages.one_day_event_date_required']]);
            } else {
                if ((!$request->has('one_day_event') && $request->one_day_event == "") && (is_null($request->from_date) || is_null($request->to_date))) {
                    return redirect()->back()->withInput()->withErrors(['navigation_id' => ['administration_messages.period_required']]);
                }
            }
        }

        $navigation = $contentPage->navigation;
        $languages  = Language::where('active', true)->get();
        $errors     = $contentPage->getUpdateInputErrors($languages, $request);
        if (count($errors) > 0) {
            return redirect()->back()->withInput()->withErrors($errors);
        }

        //One dimmension active
        if ($request->has('content_pages_one_dimensions') && $request->content_pages_one_dimensions == 1) {
            if ($request->hasFile('image')) {
                $request->validate(['image' => FileDimensionHelper::getRules(FileDimension::$CONTENT_BOX)], FileDimensionHelper::messages(FileDimension::$CONTENT_BOX));
                $request['filename'] = pathinfo($request->image->getClientOriginalName(), PATHINFO_FILENAME) . '.png';
                $contentPage->saveImage($request->file('image'));
                $pieces                   = explode(".", $request->image->getClientOriginalName());
                $name                     = $pieces[0] . '_1.' . $pieces[1];
                $request['filename_box2'] = pathinfo($name, PATHINFO_FILENAME) . '.png';
                $contentPage->copyFile($request['filename'], $request['filename_box2']);
            }
        } else {
            if ($request->hasFile('image')) {
                $request->validate(['image' => FileDimensionHelper::getRules(FileDimension::$CONTENT_BOX)], FileDimensionHelper::messages(FileDimension::$CONTENT_BOX));
                $request['filename'] = pathinfo($request->image->getClientOriginalName(), PATHINFO_FILENAME) . '.png';
                $contentPage->saveImage($request->file('image'));
            }

            if ($request->hasFile('image_box2')) {
                $pieces = explode(".", $request->image_box2->getClientOriginalName());
                $name   = $pieces[0] . '_1.' . $pieces[1];

                $request->validate(['image_box2' => FileDimensionHelper::getRules(FileDimension::$CONTENT_BOX_SECOUND)], FileDimensionHelper::messages(FileDimension::$CONTENT_BOX_SECOUND));
                $request['filename_box2'] = pathinfo($name, PATHINFO_FILENAME) . '.png';
                $contentPage->saveImage2($request->file('image_box2'), $request['filename_box2']);
            }

            if ($request->hasFile('image_box3')) {
                $pieces = explode(".", $request->image_box3->getClientOriginalName());
                $name   = $pieces[0] . '_2.' . $pieces[1];

                $request->validate(['image_box3' => FileDimensionHelper::getRules(FileDimension::$CONTENT_BOX_THIRD)], FileDimensionHelper::messages(FileDimension::$CONTENT_BOX_THIRD));
                $request['filename_box3'] = pathinfo($name, PATHINFO_FILENAME) . '.png';
                $contentPage->saveImage3($request->file('image_box3'), $request['filename_box3']);
            }
        }

        $request['position'] = $contentPage->updatedPosition($navigation, $request->navigation_id != $contentPage->navigation_id, $request);

        $contentPage->update($contentPage->getUpdateData($request));
        foreach ($languages as $language) {
            $translation = $contentPage->translations->where('language_id', $language->id)->first();
            if (is_null($translation)) {
                $contentPage->translations()->create(ContentPageTranslation::getCreateData($language, $request));
            } else {
                $translation->update($translation->getUpdateData($language, $request));
            }
            if ($language->id == 1) {
                $contentPage->tour()->update(Tour::getUpdateData($contentPage->id, $language, $request));
            }
        }

        Page::validateVisibility($contentPage, false);
        Navigation::validateVisibility($navigation, false);
        Page::generateToAdBox($contentPage, $languages);

        //        SitemapHelper::generateSiteMap();

        return redirect('admin/contents/loadContentPages/' . $contentPage->navigation_id)->with('success-message', 'administration_messages.successful_edit');
    }

    public function delete($id)
    {
        $contentPage = Page::find($id);
        if (is_null($contentPage)) {
            return redirect('admin/contents')->withErrors(['administration_messages.page_not_found']);
        }
        $navigation = $contentPage->navigation;

        if ($contentPage->galleries()->count() > 0) {
            return redirect()->back()->withErrors(['administration_messages.cant_delete_contents_page_has_galleries']);
        }

        $contentPagesToUpdate = $contentPage->navigation->content_pages()->where('position', '>', $contentPage->position)->get();
        $contentPage->delete();
        foreach ($contentPagesToUpdate as $contentPageToUpdate) {
            $contentPageToUpdate->update(['position' => $contentPageToUpdate->position - 1]);
        }

        Navigation::validateVisibility($navigation, false);

        //        SitemapHelper::generateSiteMap();

        return redirect()->back()->with('success-message', 'administration_messages.successful_delete');
    }

    public function imgDelete($id)
    {
        $contentPage = Page::find($id);
        if (is_null($contentPage)) {
            return redirect()->back()->withInput()->withErrors(['administration_messages.page_not_found']);
        }

        if (file_exists($contentPage->fullImageFilePath())) {
            unlink($contentPage->fullImageFilePath());

            return redirect()->back()->with('success-message', 'administration_messages.successful_delete_image');
        }

        return redirect()->back()->withErrors(['administration_messages.image_not_found']);
    }

    public function imgDeleteOneDimension($id)
    {
        $contentPage = Page::find($id);
        if (is_null($contentPage)) {
            return redirect()->back()->withInput()->withErrors(['administration_messages.page_not_found']);
        }

        if (file_exists($contentPage->fullImageFilePathContentbox2())) {
            unlink($contentPage->fullImageFilePathContentbox2());
        }

        if (file_exists($contentPage->fullImageFilePathContentbox1())) {
            unlink($contentPage->fullImageFilePathContentbox1());

            return redirect()->back()->with('success-message', 'administration_messages.successful_delete_image');
        }

        return redirect()->back()->withErrors(['administration_messages.image_not_found']);
    }

    public function imgDeleteContentBox2($id)
    {
        $contentPage = Page::find($id);
        if (is_null($contentPage)) {
            return redirect()->back()->withInput()->withErrors(['administration_messages.page_not_found']);
        }

        if (file_exists($contentPage->fullImageFilePathContentBox2())) {
            unlink($contentPage->fullImageFilePathContentBox2());

            return redirect()->back()->with('success-message', 'administration_messages.successful_delete_image');
        }

        return redirect()->back()->withErrors(['administration_messages.image_not_found']);
    }

    public function imgDeleteContentBox3($id)
    {
        $contentPage = Page::find($id);
        if (is_null($contentPage)) {
            return redirect()->back()->withInput()->withErrors(['administration_messages.page_not_found']);
        }

        if (file_exists($contentPage->fullImageFilePathContentBox3())) {
            unlink($contentPage->fullImageFilePathContentBox3());

            return redirect()->back()->with('success-message', 'administration_messages.successful_delete_image');
        }

        return redirect()->back()->withErrors(['administration_messages.image_not_found']);
    }

    public function deleteMultiple(Request $request)
    {
        if (!is_null($request->ids[0])) {
            $ids = array_map('intval', explode(',', $request->ids[0]));
            foreach ($ids as $id) {
                $contentPage = Page::find($id);
                if (is_null($contentPage)) {
                    continue;
                }

                if ($contentPage->galleries()->count() > 0) {
                    return redirect()->back()->withErrors(['administration_messages.cant_delete_contents_page_has_galleries']);
                }

                $contentPagesToUpdate = $contentPage->navigation->content_pages()->where('position', '>', $contentPage->position)->get();
                $contentPage->delete();
                foreach ($contentPagesToUpdate as $contentPageToUpdate) {
                    $contentPageToUpdate->update(['position' => $contentPageToUpdate->position - 1]);
                }
            }

            //            SitemapHelper::generateSiteMap();

            return redirect()->back()->with('success-message', 'administration_messages.successful_delete');
        }

        return redirect()->back()->withErrors(['administration_messages.no_checked_checkboxes']);
    }

    public function active($id, $active)
    {
        $contentPage = Page::find($id);
        if (is_null($contentPage)) {
            return redirect()->back()->withInput()->withErrors(['administration_messages.page_not_found']);
        }

        $contentPage->update(['active' => $active]);
        $translationsVisible = Page::validateVisibility($contentPage, true);
        Navigation::validateVisibility($contentPage->navigation, false);

        if (!$translationsVisible) {
            $contentPage->update(['active' => 0]);

            return redirect()->back()->withErrors(['administration_messages.page_visibility_false']);
        }

        //        SitemapHelper::generateSiteMap();

        return redirect()->back()->with('success-message', 'administration_messages.successful_edit');
    }

    public function activeMultiple($active, Request $request)
    {
        if (!is_null($request->ids[0])) {
            $hasVisibilityFalse = false;
            $ids                = array_map('intval', explode(',', $request->ids[0]));

            foreach ($ids as $id) {
                $contentPage         = Page::where('id', $id)->first();
                $translationsVisible = Page::validateVisibility($contentPage, true);

                if (!$translationsVisible) {
                    $contentPage->update(['active' => 0]);
                    $hasVisibilityFalse = true;
                } else {
                    $contentPage->update(['active' => $active]);
                }
            }

            Navigation::validateVisibility($contentPage->navigation, false);

            if ($hasVisibilityFalse) {
                return redirect()->back()->withErrors(['administration_messages.some_pages_visibility_false']);
            }
        }

        //        SitemapHelper::generateSiteMap();

        return redirect()->back()->with('success-message', 'administration_messages.successful_edit');
    }

    public function positionDown($id)
    {
        $contentPage = Page::find($id);
        if (is_null($contentPage)) {
            return redirect()->back()->withInput()->withErrors(['administration_messages.page_not_found']);
        }

        $nextContentPage = $contentPage->navigation->content_pages()->where('position', $contentPage->position + 1)->first();
        if (!is_null($nextContentPage)) {
            $nextContentPage->update(['position' => $nextContentPage->position - 1]);
            $contentPage->update(['position' => $contentPage->position + 1]);
        }

        return redirect()->back()->with('success-message', 'administration_messages.successful_edit');
    }

    public function positionUp($id)
    {
        $contentPage = Page::find($id);
        if (is_null($contentPage)) {
            return redirect()->back()->withInput()->withErrors(['administration_messages.page_not_found']);
        }

        $prevContentPage = $contentPage->navigation->content_pages()->where('position', $contentPage->position - 1)->first();
        if (!is_null($prevContentPage)) {
            $prevContentPage->update(['position' => $prevContentPage->position + 1]);
            $contentPage->update(['position' => $contentPage->position - 1]);
        }

        return redirect()->back()->with('success-message', 'administration_messages.successful_edit');
    }

    public function makeAd($id)
    {
        $contentPage = Page::find($id);
        if (is_null($contentPage)) {
            return redirect()->back()->withInput()->withErrors(['administration_messages.page_not_found']);
        }

        $contentPage->makeAdBox();

        return redirect()->back()->with('success-message', 'administration_messages.successfully_added_to_adboxes');
    }
}
