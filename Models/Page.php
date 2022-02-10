<?php

namespace Modules\Page\Models;

use App\Classes\FileHelper;
use App\Classes\LanguageHelper;
use App\Classes\SlugHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Page extends Model
{
    public static $IMAGES_PATH = "images/pages";
    protected     $table       = "pages";
    protected     $fillable    = ['navigation_id', 'from_price', 'price', 'from_date', 'to_date', 'show_in_homepage', 'active', 'position', 'creator_user_id', 'show_in_homepage_type', 'one_day_event', 'one_day_event_date', 'filename', 'filename_box2', 'filename_box3', 'in_ad_box', 'product_id'];

    public static function getCreateInputErrors($languages, $request)
    {
        $errors     = [];
        $navigation = Navigation::where('id', $request->navigation_id)->where('module_id', Navigation::$CONTENT_MODULE_ID)->first();
        if (is_null($navigation)) {
            $errors['navigation_id'] = 'administration_messages.page_invalid';
        }

        foreach ($navigation->content_pages as $page) {
            foreach ($languages as $language) {
                $langTitle = 'title_' . $language->code;
                if (!$request->has($langTitle) || $request[$langTitle] == "") {
                    $errors[$langTitle] = 'administration_messages.title_required';
                } else {
                    $translation = $page->translations->where('language_id', $language->id)->first();
                    if (!is_null($translation)) {
                        $wantedSlug = ContentPageTranslation::where('slug', SlugHelper::makeSlug($language->code, $request[$langTitle]))->first();
                        if (!is_null($wantedSlug) && $translation->slug == $wantedSlug->slug) {
                            $errors[$langTitle] = 'administration_messages.title_exists';
                        }
                    }
                }
            }
        }

        return $errors;
    }
    public static function generatePosition($navigation, $request)
    {
        $contentPages = $navigation->content_pages()->orderBy('position', 'desc')->get();
        if (count($contentPages) < 1) {
            return 1;
        }
        if (!$request->has('position') || is_null($request['position'])) {
            return $contentPages->first()->position + 1;
        }

        if ($request['position'] > $contentPages->first()->position) {
            return $contentPages->first()->position + 1;
        }

        $contentPagesUpdate = $navigation->content_pages()->where('position', '>=', $request['position'])->get();
        foreach ($contentPagesUpdate as $contentPageUpdate) {
            $contentPageUpdate->update(['position' => $contentPageUpdate->position + 1]);
        }

        return $request['position'];
    }
    public static function getCreateData($request)
    {
        $data                    = self::getRequestData($request);
        $data['creator_user_id'] = Auth::user()->id;

        return $data;
    }
    private static function getRequestData($request)
    {
        $data = [
            'navigation_id' => $request->navigation_id,
            'position'      => $request->position
        ];
        if ($request->has('price')) {
            $data['price'] = $request->price;
        }

        if ($request->has('one_day_event_date') && $request->one_day_event_date != "" && ($request->has('one_day_event') && $request->has('one_day_event') == "on")) {
            $data['one_day_event_date'] = Carbon::parse($request->one_day_event_date)->format('Y-m-d');
        } else {
            $data['from_date'] = null;
            $data['to_date']   = null;

            if ($request->has('from_date') && $request->from_date != "") {
                $data['from_date'] = Carbon::parse($request->from_date)->format('Y-m-d');
            }

            if ($request->has('to_date') && $request->to_date != "") {
                $data['to_date'] = Carbon::parse($request->to_date)->format('Y-m-d');
            }

            $data['one_day_event_date'] = null;
        }

        $data['show_in_homepage'] = false;
        if ($request->has('show_in_homepage')) {
            $data['show_in_homepage'] = filter_var($request->show_in_homepage, FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->has('show_in_homepage_type')) {
            $data['show_in_homepage_type'] = $request->show_in_homepage_type;
        }

        $data['from_price'] = 0;
        if ($request->has('from_price')) {
            $data['from_price'] = 1;
        }

        $data['one_day_event'] = 0;
        if ($request->has('one_day_event')) {
            $data['one_day_event'] = 1;
            $data['from_date']     = null;
            $data['to_date']       = null;
        }

        $data['active'] = false;
        if ($request->has('active')) {
            $data['active'] = filter_var($request->active, FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->has('filename')) {
            $data['filename'] = $request->filename;
        }

        $data['in_ad_box'] = false;
        if ($request->has('in_ad_box')) {
            $data['in_ad_box'] = filter_var($request->in_ad_box, FILTER_VALIDATE_BOOLEAN);
        }

        return $data;
    }
    public static function boot()
    {
        parent::boot();

        self::deleted(function (ContentPage $contentPage) {
            $contentPage->galleries()->delete();
            if (file_exists($contentPage->directoryPath())) {
                FileHelper::deleteDirectory($contentPage->directoryPath());
            }
        });
    }
    public function galleries()
    {
        return Gallery::where('parent_type_id', Gallery::$CONTENT_PAGE_TYPE_ID)->where('parent_id', $this->id);
    }
    public function directoryPath()
    {
        return public_path(self::$IMAGES_PATH . '/' . $this->id);
    }
    public static function validateVisibility($contentPage, $withReturn)
    {
        $visible   = false;
        $languages = Language::where('active', true)->get();

        foreach ($languages as $language) {
            $translationVisible = $contentPage->translations()->where('language_id', $language->id)->value('visible');
            if ($translationVisible) {
                $visible = true;
            }
        }

        if ($visible === false) {
            $contentPage->update(['active' => 0]);
        }

        if ($withReturn) {
            return $visible;
        }
    }
    public static function generateToAdBox($contentPage, $languages)
    {
        $contentPageAdBox = AdBox::where('content_page_id', $contentPage->id)->first();

        if ($contentPage->in_ad_box == "on" && is_null($contentPageAdBox)) {
            $defaultLanguage = Language::where('code', env('DEF_LANG_CODE'))->first();
            $data            = new Request();
            foreach ($languages as $language) {
                $data['visible_' . $language->code]           = false;
                $data['title_' . $language->code]             = $contentPage->translations()->where('language_id', $language->id)->first()->title;
                $data['short_description_' . $language->code] = $contentPage->translations()->where('language_id', $language->id)->first()->short_description;
                $data['url_' . $language->code]               = self::generateUrl($contentPage, $language);
            }
            $data['position']        = AdBox::generatePosition($data);
            $data['content_page_id'] = $contentPage->id;

            $adBox = AdBox::create(AdBox::getCreateData($data));
            foreach ($languages as $language) {
                $adBox->translations()->create(AdBoxTranslation::getCreateData($language, $data));
            }
        }
    }
    public static function generateUrl($contentPage, $language)
    {
        $page = $contentPage->translations()->where('language_id', $language->id)->first();
        if (is_null($page)) {
            return null;
        }
        $navigation = $contentPage->navigation->translations()->where('language_id', $language->id)->first();
        if (is_null($navigation)) {
            return null;
        }

        return url($language->code . '/page/' . $navigation->slug . '/' . $page->slug);
    }
    public function navigation()
    {
        return $this->belongsTo(Navigation::class);
    }
    public function product()
    {
        return $this->hasOne(Product::class, 'id', 'product_id');
    }
    public function getUpdateInputErrors($languages, $request)
    {
        $errors = [];
        if ($request->navigation_id != $this->navigation_id) {
            $navigation = Navigation::where('id', $request->navigation_id)->where('module_id', Navigation::$CONTENT_MODULE_ID)->first();
            if (is_null($navigation)) {
                $errors['navigation_id'] = 'administration_messages.page_invalid';
            }

            foreach ($navigation->content_pages as $page) {
                foreach ($languages as $language) {
                    $langTitle = 'title_' . $language->code;

                    $translation = $page->translations->where('language_id', $language->id)->first();
                    if (!is_null($translation)) {
                        $wantedSlug = ContentPageTranslation::where('slug', SlugHelper::makeSlug($language->code, $request[$langTitle]))->first();
                        if (!is_null($wantedSlug) && $translation->slug == $wantedSlug->slug && $translation->title == $request[$langTitle]) {
                            $errors[$langTitle] = 'administration_messages.title_exists';
                        }
                    }
                }
            }
        }

        foreach ($languages as $language) {
            $langTitle = 'title_' . $language->code;
            if (!$request->has($langTitle) || $request[$langTitle] == "") {
                $errors[$langTitle] = 'administration_messages.title_required';
            } else {
                $slug      = ContentPageTranslation::where('slug', SlugHelper::makeSlug($language->code, $request[$langTitle]))->first();
                $rules     = array('slug' => 'unique:content_page_translation,slug,' . $this->id . ',content_page_id,' . 'language_id,' . $language->id);
                $validator = \Validator::make(array('slug' => $slug), $rules);
                if ($validator->fails()) {
                    return $errors[$langTitle] = 'administration_messages.title_exists';
                }
            }
        }

        return $errors;
    }
    public function updatedPosition($navigation, $isNew, $request)
    {
        if (!$request->has('position') || is_null($request->position) || (!$isNew && $request->position == $this->position)) {
            return $this->position;
        }

        if ($isNew) {
            $contentPages = $navigation->content_pages()->orderBy('position', 'desc')->get();
        } else {
            $contentPages = $this->navigation->content_pages()->orderBy('position', 'desc')->get();
        }

        if ($contentPages->count() < 1) {
            $request['position'] = 1;
        } else {
            if ($request['position'] > $contentPages->first()->position) {
                $request['position'] = $contentPages->first()->position;
            } elseif ($request['position'] < $contentPages->last()->position) {
                $request['position'] = $contentPages->last()->position;
            }
        }

        if ($isNew) {
            $contentPagesToUpdate = $navigation->content_pages()->where('position', '>=', $request['position'])->get();
            foreach ($contentPagesToUpdate as $contentPageToUpdate) {
                $contentPageToUpdate->update(['position' => $contentPageToUpdate->position + 1]);
            }
            $contentPagesToUpdate = $this->navigation->content_pages()->where('position', '>', $this->position)->get();
            foreach ($contentPagesToUpdate as $contentPageToUpdate) {
                $contentPageToUpdate->update(['position' => $contentPageToUpdate->position - 1]);
            }
        } else if ($request['position'] >= $this->position) {
            $contentPagesToUpdate = $this->navigation->content_pages()->where('id', '<>', $this->id)->where('position', '>', $this->position)->where('position', '<=', $request['position'])->get();
            foreach ($contentPagesToUpdate as $contentPageToUpdate) {
                $contentPageToUpdate->update(['position' => $contentPageToUpdate->position - 1]);
            }
        } else {
            $contentPagesToUpdate = $this->navigation->content_pages()->where('id', '<>', $this->id)->where('position', '<', $this->position)->where('position', '>=', $request['position'])->get();
            foreach ($contentPagesToUpdate as $contentPageToUpdate) {
                $contentPageToUpdate->update(['position' => $contentPageToUpdate->position + 1]);
            }
        }

        return $request['position'];
    }
    public function getUpdateData($request)
    {
        $data                    = self::getRequestData($request);
        $data['creator_user_id'] = $this->creator_user_id;

        return $data;
    }
    public function icons()
    {
        return Icon::where('parent_type_id', Icon::$CONTENT_PAGE_TYPE_ID)->where('parent_id', $this->id);
    }
    public function logos()
    {
        return Logo::where('parent_type_id', Logo::$CONTENT_PAGE_TYPE_ID)->where('parent_id', $this->id);
    }
    public function catalogs()
    {
        return Catalog::where('parent_type_id', Catalog::$CONTENT_PAGE_TYPE_ID)->where('parent_id', $this->id);
    }
    public function additional_galleries()
    {
        return AdditionalGallery::where('parent_type_id', AdditionalGallery::$CONTENT_PAGE_TYPE_ID)->where('parent_id', $this->id);
    }
    public function fullImageFilePath()
    {
        if ($this->navigation->vizualization_type_id == Navigation::$LIST_VISUALISATION || $this->navigation->vizualization_type_id == Navigation::$KARETA_FIRST_VISUALISATION) {
            return public_path(self::$IMAGES_PATH . '/' . $this->id) . '/' . $this->filename;
        }

        if ($this->navigation->vizualization_type_id == Navigation::$KARETA_SECOND_VISUALISATION || $this->navigation->vizualization_type_id == Navigation::$KARETA_SECOND_FILTER_VISUALISATION) {
            return public_path(self::$IMAGES_PATH . '/' . $this->id) . '/' . $this->filename_box2;
        }

        if ($this->navigation->vizualization_type_id == Navigation::$KARETA_THIRD_VISUALISATION) {
            return public_path(self::$IMAGES_PATH . '/' . $this->id) . '/' . $this->filename3;
        }
    }
    public function fullImageFilePathContentbox1()
    {
        return public_path(self::$IMAGES_PATH . '/' . $this->id) . '/' . $this->filename;
    }
    public function fullImageFilePathContentbox2()
    {
        return public_path(self::$IMAGES_PATH . '/' . $this->id) . '/' . $this->filename_box2;
    }
    public function fullImageFilePathContentbox3()
    {
        return public_path(self::$IMAGES_PATH . '/' . $this->id) . '/' . $this->filename_box3;
    }
    public function fullImageFilePathUrl()
    {
        //Vadim variant CONTENT_BOX 1
        if ($this->navigation->vizualization_type_id == Navigation::$LIST_VISUALISATION || $this->navigation->vizualization_type_id == Navigation::$KARETA_FIRST_VISUALISATION) {
            if ($this->filename == '' || !file_exists(public_path(self::$IMAGES_PATH . '/' . $this->id . '/' . $this->filename))) {
                return url('admin/assets/system_images/content_box_img.png');
            }

            return url(self::$IMAGES_PATH . '/' . $this->id) . '/' . $this->filename;
        }

        //Vadim variant CONTENT_BOX 2
        if ($this->navigation->vizualization_type_id == Navigation::$KARETA_SECOND_VISUALISATION || $this->navigation->vizualization_type_id == Navigation::$KARETA_SECOND_FILTER_VISUALISATION) {
            if ($this->filename_box2 == '' || !file_exists(public_path(self::$IMAGES_PATH . '/' . $this->id . '/' . $this->filename_box2))) {
                return url('admin/assets/system_images/content_box2_img.png');
            }

            return url(self::$IMAGES_PATH . '/' . $this->id) . '/' . $this->filename_box2;
        }

        //Vadim variant CONTENT_BOX 3
        if ($this->navigation->vizualization_type_id == Navigation::$KARETA_THIRD_VISUALISATION) {
            if ($this->filename_box3 == '' || !file_exists(public_path(self::$IMAGES_PATH . '/' . $this->id . '/' . $this->filename_box3))) {
                return url('admin/assets/system_images/content_box3_img.png');
            }

            return url(self::$IMAGES_PATH . '/' . $this->id) . '/' . $this->filename_box3;
        }

        return url('admin/assets/system_images/header_inner_page_img.png');
    }
    public function fullImageFilePathUrlContentBox1()
    {
        if ($this->filename == '' || !file_exists(public_path(self::$IMAGES_PATH . '/' . $this->id . '/' . $this->filename))) {
            return url('admin/assets/system_images/content_box_img.png');
        }

        return url(self::$IMAGES_PATH . '/' . $this->id) . '/' . $this->filename;
    }
    public function fullImageFilePathUrlContentBox2()
    {
        if (!file_exists(public_path(self::$IMAGES_PATH . '/' . $this->id . '/' . $this->filename_box2)) || $this->filename_box2 == '') {
            return url('admin/assets/system_images/content_box2_img.png');
        }

        return url(self::$IMAGES_PATH . '/' . $this->id) . '/' . $this->filename_box2;
    }
    public function fullImageFilePathUrlContentBox3()
    {
        if (!file_exists(public_path(self::$IMAGES_PATH . '/' . $this->id . '/' . $this->filename_box3)) || $this->filename_box3 == '') {
            return url('admin/assets/system_images/content_box3_img.png');
        }

        return url(self::$IMAGES_PATH . '/' . $this->id) . '/' . $this->filename_box3;
    }
    public function saveImage($image)
    {
        // if (file_exists($this->directoryPath())) {
        //     FileHelper::deleteDirectory($this->directoryPath());
        // }

        FileHelper::saveFile(public_path(self::$IMAGES_PATH . '/' . $this->id), $image, $image->getClientOriginalName(), pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME) . '.png');
    }
    public function saveImage2($image, $name)
    {
        // if (file_exists($this->directoryPath())) {
        //     FileHelper::deleteDirectory($this->directoryPath());
        // }

        FileHelper::saveFile(public_path(self::$IMAGES_PATH . '/' . $this->id), $image, $name, pathinfo($name, PATHINFO_FILENAME) . '.png');
    }
    public function saveImage3($image, $name)
    {
        FileHelper::saveFile(public_path(self::$IMAGES_PATH . '/' . $this->id), $image, $name, pathinfo($name, PATHINFO_FILENAME) . '.png');
    }
    public function copyFile($targetFileName, $name)
    {
        FileHelper::copyFile(public_path(self::$IMAGES_PATH . '/' . $this->id) . '/' . $targetFileName, public_path(self::$IMAGES_PATH . '/' . $this->id) . '/' . $name);
    }
    public function makeAdBox()
    {
        $languages = LanguageHelper::getActiveLanguages();
        $data      = new Request();
        foreach ($languages as $language) {
            $contentPageTranslation                       = $this->translations()->where('language_id', $language->id)->first();
            $navigation                                   = $this->navigation->translations()->where('language_id', $language->id)->first();
            $data['visible_' . $language->code]           = true;
            $data['title_' . $language->code]             = $contentPageTranslation->title;
            $data['short_description_' . $language->code] = $contentPageTranslation->short_description;
            $data['url_' . $language->code]               = $language->code . '/page/' . $navigation->slug . '/' . $contentPageTranslation->slug;
        }
        $data['price']     = $this->price;
        $data['from_date'] = Carbon::parse($this->created_at)->format('Y-m-d');
        $data['position']  = AdBox::generatePosition($data, 0);

        $adBox = AdBox::create(AdBox::getCreateData($data));
        foreach ($languages as $language) {
            $adBox->translations()->create(AdBoxTranslation::getCreateData($language, $data));
        }
    }
    public function translations()
    {
        return $this->hasMany(ContentPageTranslation::class);
    }

    public function currentTranslation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        $currentLanguage = LanguageHelper::getCurrentLanguage();

        return $this->hasOne(ContentPageTranslation::class)->where('language_id', $currentLanguage->id);
    }

    public function defaultTranslation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        $defaultLanguage = LanguageHelper::getDefaultLanguage();

        return $this->hasOne(ContentPageTranslation::class)->where('language_id', $defaultLanguage->id);
    }

    public function tour()
    {
        return $this->hasOne(Tour::class, 'page_id');
    }
}
