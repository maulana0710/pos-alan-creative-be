<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class UploadFileService
{

    static public function upload($file,$path = 'file')
    {
       if($file){
            $filenameWithExt = $file->getClientOriginalName();
            $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();
            $filenameSimpan = $filename.'_'.time().'.'.$extension;
            $path = $file->storeAs($path, $filenameSimpan,'public');
            return $path;
       }else{
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('File not found');
       }
    }

    static public function remove($path){
        if ($path && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }
    }
}
