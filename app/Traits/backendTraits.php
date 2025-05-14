<?php

namespace App\Traits;

use Intervention\Image\Facades\Image;

trait  backendTraits
{

    // save image
    function saveImage($photo, $folder)
    {
        //save photo in folder
        $file_extension = $photo->getClientOriginalExtension();
        $file_name = time() . '.' . $file_extension;
        $path = $folder;
        $photo->move($path, $file_name);
        return $file_name;
    }
    // public function upploadImage($image,$folder)
    // {
    //     $imageName = time() .'.'.$image->extension();
    //     $image->move(public_path($folder),$imageName);
    //     return $imageName;
    // }
    function upploadImage($photo_name, $folder)
    {
        $image = $photo_name;
        $image_name = time() . '' . $image->getClientOriginalName();
        $destinationPath = public_path($folder);
        $image->move($destinationPath, $image_name);
        return $image_name;
    }

    function deleteFile($photo_name, $folder)
    {
        $image_name = $photo_name;
        $image_path = public_path($folder) . $image_name;
        if (file_exists($image_path)) {
            @unlink($image_path);
        }
    }


    // save image by Image Intervention
    function imageInterve($image, $path)
    {
        Image::make($image)->resize(300, null, function ($constraint) {
            $constraint->aspectRatio();
        })
            ->save(public_path($path . $image->hashName()));
        $image = $image->hashName();
        return $image;
    }
} // end of backendTraits
