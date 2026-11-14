<?php

namespace App\Helpers;

class DescriptionImageHelper
{
    private const ALLOWED_EXTS = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    /**
     * Lưu tất cả ảnh Base64 trong HTML description ra file thật
     * và thay src thành đường dẫn file
     */
    public static function saveImages($html)
    {
        if (!is_string($html) || $html === '') {
            return '';
        }

        return preg_replace_callback('/<img\s+[^>]*src="data:image\/([a-zA-Z0-9+]+);base64,([^"]*)"[^>]*>/', function ($matches) {
            $rawExt = strtolower($matches[1]);
            $ext = $rawExt === 'jpeg' ? 'jpg' : $rawExt;
            if (!in_array($ext, self::ALLOWED_EXTS, true)) {
                return '';
            }

            $data = base64_decode($matches[2], true);
            if ($data === false) {
                return '';
            }

            $filename = uniqid('', true) . '.' . $ext;
            $dir = public_path('description_images');
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($dir . '/' . $filename, $data);

            return '<img src="' . url('description_images/' . $filename) . '" />';
        }, $html);
    }
}
