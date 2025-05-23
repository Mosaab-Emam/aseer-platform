<?php
namespace App\Helpers;
use App\Models\MenuLink;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

class MainHelper
{

    protected static $lowerLimit = 70;
    protected static $upperLimit = 255;
    protected static $colorGap = 20;
    protected static $generated = array();

    public static function notify_user(
        $options = []
    )
    {
        $options = array_merge([
            'user_id' => 1,
            'content' => [],
            'action_url' => "",
            'methods' => ['database'],
            'image' => "",
            'btn_text' => "عرض الإشعار"
        ], $options);
        $user = \App\Models\User::where('id', $options['user_id'])->first();
        if ($user != null) {
            \App\Models\User::where('email', $user->email)->first()->notify(
                (new \App\Notifications\GeneralNotification())
                    ->setContent($options['content'])
                    ->setMethods($options['methods'])
                    ->setActionUrl($options['action_url'])
                    ->setActionText($options['btn_text'])
                    ->setActionText($options['image'])
            );
        }
    }

    public static function recaptcha($cap)
    {

        $ipAddress = 'NA';
        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
            $ipAddress = $_SERVER["HTTP_CF_CONNECTING_IP"];
        } else {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        }

        $url = 'https://www.google.com/recaptcha/api/siteverify';
        //$remoteip = $_SERVER['REMOTE_ADDR'];
        $data = [
            'secret' => env("RECAPTCHA_SECRET_KEY"),
            'response' => $cap,
            'remoteip' => $ipAddress
        ];
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $resultJson = json_decode($result);

        if ($resultJson->success != true) {
            return 0;
        } else {
            return json_decode(json_encode($resultJson), true)['score'];
        }

    }

    public static function notify_visitors(
        $options = []
    )
    {
        $options = array_merge([
            'emails' => ["admin@admin.com"],
            'content' => [],
            'action_url' => "",
            'methods' => ['mail'],
            'image' => "",
            'btn_text' => "عرض الإشعار"
        ], $options);
        Notification::route('mail', $options['emails'])
            ->notify((new \App\Notifications\GeneralNotification())
                ->setContent($options['content'])
                ->setMethods($options['methods'])
                ->setActionUrl($options['action_url'])
                ->setActionText($options['btn_text'])
                ->setActionText($options['image']));
    }

    public static function make_error_report(
        $options = []
    )
    {
        $options = array_merge([
            'error' => "",
            'error_code' => "",
            'details' => json_encode(request()->all())
        ], $options);
        try {
            if (Schema::hasTable('report_errors'))
                \App\Models\ReportError::create([
                    'user_id' => (auth()->check() ? auth()->user()->id : null),
                    'title' => $options['error'],
                    'code' => $options['error_code'],
                    'url' => url()->previous(),
                    'ip' => \UserSystemInfoHelper::get_ip(),
                    'user_agent' => request()->header('User-Agent'),
                    'request' => json_encode(request()->all()),
                    'description' => $options['details']
                ]);
        } catch (\Exception $e) {
        }
    }

    public static function binaryToString($binary)
    {
        $binaries = explode(' ', $binary);

        $string = null;
        foreach ($binaries as $binary) {
            $string .= pack('H*', dechex(bindec($binary)));
        }

        return $string;
    }

    /*/src=[\"\'][^\'\']+[\"\']/*/

    public static function rate_limit_insert()
    {
        $prev_url = "";
        $prev_domain = "";
        if (filter_var(url()->previous(), FILTER_VALIDATE_URL)) {
            $parsex = parse_url(url()->previous());
            $prev_domain = $parsex['host'];
            $prev_domain = "";
            try {
                $prev_url = url()->previous();
                $prev_domain = $parsex['host'];
            } catch (\Exception $e) {
            }
        }
        $data = [
            'traffic_landing' => request()->fullUrl(),
            'ip' => \UserSystemInfoHelper::get_ip(),
            'prev_url' => $prev_url,
            'prev_domain' => $prev_domain,
            'agent_name' => request()->header('User-Agent'),
            'user_id' => auth()->check() ? auth()->user()->id : null,
            'browser' => \UserSystemInfoHelper::get_browsers(),
            'device' => \UserSystemInfoHelper::get_device(),
            'operating_system' => \UserSystemInfoHelper::get_os()
        ];
        \App\Jobs\RateLimitInsertJob::dispatch($data);
    }

    public static function focus_urls($string)
    {
        $url_regex = '~(http|ftp)s?://[a-z0-9.-]+\.[a-z]{2,7}(/\S*)?~i';
        return preg_replace($url_regex, " <a href='$0' target='_blank' rel='nofollow' style='font-family: inherit;'>$0</a> ", urldecode(htmlspecialchars($string)));
    }

    public static function slug($string)
    {
        $t = $string;
        $specChars = array(
            ' ' => '-', '!' => '', '"' => '',
            '#' => '', '$' => '', '%' => '',
            '&amp;' => '', '&nbsp;' => '',
            '\'' => '', '(' => '',
            ')' => '', '*' => '', '+' => '',
            ',' => '', '₹' => '', '.' => '',
            '/-' => '', ':' => '', ';' => '',
            '<' => '', '=' => '', '>' => '',
            '?' => '', '@' => '', '[' => '',
            '\\' => '', ']' => '', '^' => '',
            '_' => '', '`' => '', '{' => '',
            '|' => '', '}' => '', '~' => '',
            '-----' => '-', '----' => '-', '---' => '-',
            '/' => '', '--' => '-', '/_' => '-',
        );
        foreach ($specChars as $k => $v) {
            $t = str_replace($k, $v, $t);
        }

        return substr($t, 0, 230);
    }

    public static function menuLinkGenerator(MenuLink $link)
    {
        if ($link->type == "CUSTOM_LINK") {
            return $link->url;
        } elseif ($link->type == "PAGE") {
            $page = \App\Models\Page::where('id', $link->type_id)->first();
            if ($page == null)
                return env("APP_URL");
            return route('page.show', $page);
        } elseif ($link->type == "CATEGORY") {
            $category = \App\Models\Category::where('id', $link->type_id)->first();
            if ($category == null)
                return env("APP_URL");
            return route('category.show', $category);
        }
    }

    public static function get_conversion($file_name, $conversion = "original", $new_extension = "webp")
    {
        if ($new_extension == "main" || $conversion == null)
            $new_extension = pathinfo($file_name, PATHINFO_EXTENSION);
        $explode = explode("/", $file_name);
        if (isset($explode[0]) && isset($explode[1]) && $conversion != null) {
            $new_file_name = pathinfo($file_name, PATHINFO_FILENAME) . '-' . $conversion . '.' . $new_extension;
            return $explode[0] . '/' . "conversions" . '/' . $new_file_name;
        }
        return $file_name;
    }

    public static function move_media_to_model_by_id($id, $model, $collection = "default")
    {
        $temp_files = \App\Models\TempFile::where('name', $id)->with(['media'])->get();
        foreach ($temp_files as $file) {
            foreach ($file->media as $media) {
                $media->move($model, $collection);
            }
        }
        return 1;
    }

    public static function get_validations($type = "file")
    {
        if ($type == "file")
            return "3gp,7z,7zip,ai,apk,avi,bin,bmp,bz2,css,csv,doc,docx,egg,flv,gif,gz,h264,htm,html,ia,icns,ico,jpeg,jpg,m4v,markdown,md,mdb,mkv,mov,mp3,mp4,mpa,mpeg,mpg,mpga,octet-stream,odp,ods,odt,ogg,otf,pak,pdf,pea,png,pps,ppt,pptx,psd,rar,rm,rss,rtf,s7z,sql,svg,tar,targz,tbz2,tex,tgz,tif,tiff,tlz,ttf,vob,wav,webm,wma,wmv,xhtml,xlr,xls,xlsx,xml,z,zip,zipx,gif,png,jpeg,qt";
        else if ($type == "image")
            return "jpeg,bmp,png,gif";
    }

    public static function allowed_files_mimetypes($type = "file")
    {

        $file = ['video/3gpp', 'application/x-7z-compressed', 'application/x-7z-compressed', 'application/postscript', 'application/vnd.android.package-archive', 'video/x-msvideo', 'application/octet-stream', 'image/bmp', 'application/x-bzip2', 'text/css', 'text/csv', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.python.egg', 'video/x-flv', 'image/gif', 'application/gzip', 'video/h264', 'text/html', 'text/html', 'application/itch', 'image/icns', 'image/vnd.microsoft.icon', 'image/jpeg', 'image/jpeg', 'video/x-m4v', 'text/markdown', 'text/markdown', 'application/vnd.ms-access', 'video/x-matroska', 'video/quicktime', 'audio/mpeg', 'video/mp4', 'video/mpeg', 'video/mpeg', 'audio/mpeg', 'application/octet-stream', 'application/vnd.oasis.opendocument.presentation', 'application/vnd.oasis.opendocument.spreadsheet', 'application/vnd.oasis.opendocument.text', 'audio/ogg', 'font/otf', 'application/x-pak', 'application/pdf', 'application/x-pea', 'image/png', 'application/vnd.ms-powerpoint', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'image/vnd.adobe.photoshop', 'application/x-rar-compressed', 'application/vnd.rn-realmedia', 'application/rss+xml', 'application/rtf', 'application/x-7z-compressed', 'application/sql'/*,'image/svg+xml'*/, 'application/x-tar', 'application/gzip', 'application/x-bzip2', 'application/x-tex', 'application/gzip', 'image/tiff', 'image/tiff', 'application/x-lzma', 'font/ttf', 'text/plain', 'video/dvd', 'audio/wav', 'video/webm', 'audio/x-ms-wma', 'video/x-ms-wmv', 'application/xhtml+xml', 'application/vnd.ms-excel', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/xml', 'application/x-compress', 'application/zip', 'application/x-zip-compressed'];

        $image = ['image/bmp', 'image/gif', 'image/jpeg', 'image/jpeg', 'image/png', 'image/vnd.microsoft.icon', 'image/jpeg', 'image/tiff', 'image/tiff'/*,'image/svg+xml'*/, 'image/png', 'image/vnd.adobe.photoshop'];

        $video = ['video/3gpp', 'video/x-msvideo', 'video/x-flv', 'video/h264', 'video/x-m4v', 'video/quicktime', 'video/mp4', 'video/mpeg', 'video/mpeg', 'video/x-matroska', 'video/vnd.rn-realmedia', 'video/webm', 'video/x-ms-wma', 'video/x-ms-wmv', 'video/dvd'];

        $pdf = ['application/pdf'];

        if ($type == "file")
            return $file;
        elseif ($type == "image")
            return $image;
        elseif ($type == "video")
            return $video;
        elseif ($type == "pdf")
            return $pdf;
        else
            return [];
    }

    public static function convert_to_embed($link)
    {
        // Check if the link is from YouTube
        $youtubeRegex = '/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/';

        if (preg_match($youtubeRegex, $link, $matches)) {
            // Extract the video ID and return the embed code
            $videoId = $matches[1];
            return "https://www.youtube.com/embed/$videoId";
        } else {
            // Return the link as it is if it's not a YouTube video
            return $link;
        }
    }


    public static function get_block_content($component_content)
    {
        $contents = [];
        $items = [];
        if ($component_content['type'] == "categories") {
            $query = \App\Models\Category::query();
            $items = $query->where(function ($q) use ($component_content) {
                if (is_array($component_content['selected_ids']) && count($component_content['selected_ids']) > 0)
                    $q->whereIn('id', $component_content['selected_ids']);
            })->simplePaginate($component_content['items_count']);
            foreach ($items as $item) {
                $contents[] = [
                    'title' => $item->title,
                    'image' => $item->image(),
                    'description' => $item->description,
                    'url' => route('category.show', ['category' => $item]),
                ];
            }
        } elseif ($component_content['type'] == "articles") {
            $query = \App\Models\Article::query();
            $items = $query->where(function ($q) use ($component_content) {
                if (is_array($component_content['selected_ids']) && count($component_content['selected_ids']) > 0)
                    $q->whereIn('id', $component_content['selected_ids']);
            })->simplePaginate($component_content['items_count']);
            foreach ($items as $item) {
                $contents[] = [
                    'title' => $item->title,
                    'image' => $item->main_image(),
                    'description' => $item->description,
                    'url' => route('article.show', ['article' => $item]),
                ];
            }
        } elseif ($component_content['type'] == "categories_articles") {
            $query = \App\Models\Article::query();
            $items = $query->where(function ($q) use ($component_content) {
                if (is_array($component_content['selected_ids']) && count($component_content['selected_ids']) > 0)
                    $q->whereHas('categories', function ($q) use ($component_content) {
                        $q->whereIn('category_id', $component_content['selected_ids']);
                    });
            })->simplePaginate($component_content['items_count']);
            foreach ($items as $item) {
                $contents[] = [
                    'title' => $item->title,
                    'image' => $item->main_image(),
                    'description' => $item->description,
                    'url' => route('article.show', ['article' => $item]),
                ];
            }
        }
        return [
            'contents' => $contents,
            'component_content' => $component_content,
            //'items'=>$items,
            'html' => view('components.component-content', [
                'component_content' => $component_content,
                'items' => $items,
                'contents' => $contents
            ])->render()
        ];
    }











}
