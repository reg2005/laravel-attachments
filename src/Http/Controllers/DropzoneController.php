<?php

namespace Bnb\Laravel\Attachments\Http\Controllers;

use Bnb\Laravel\Attachments\Attachment;
use Event;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lang;

class DropzoneController extends Controller
{

    public function post(Request $request)
    {
        if (Event::dispatch('attachments.dropzone.uploading', [$request], true) === false) {
            return response(['status' => 403, 'message' => Lang::get('attachments::messages.errors.upload_denied')], 403);
        }

        $file = (new Attachment(array_only($request->input(), [
            'title',
            'description',
            'key'
        ])))
            ->fromPost($request->file($request->input('file_key', 'file')));

        $file->metadata = ['dz_session_key' => csrf_token()];

        if ($file->save()) {
            return array_only($file->toArray(), [
                'uuid',
                'url',
                'filename',
                'filetype',
                'filesize',
                'title',
                'description',
                'key'
            ]);
        }

        return response(['status' => 500, 'message' => Lang::get('attachments::messages.errors.upload_failed')], 500);
    }


    public function delete($id, Request $request)
    {
        if ($file = Attachment::where('uuid', $id)->first()) {
            /** @var Attachment $file */

            if ($file->model_type || $file->model_id) {
                return response(['status' => 422, 'message' => Lang::get('attachments::messages.errors.delete_denied')], 422);
            }

            if (filter_var(config('attachments.behaviors.dropzone_check_csrf'), FILTER_VALIDATE_BOOLEAN) &&
                $file->metadata('dz_session_key') !== csrf_token()
            ) {
                return response(['status' => 401, 'message' => Lang::get('attachments::messages.errors.delete_denied')], 401);
            }

            if (Event::dispatch('attachments.dropzone.deleting', [$request, $file], true) === false) {
                return response(['status' => 403, 'message' => Lang::get('attachments::messages.errors.delete_denied')], 403);
            }

            $file->delete();
        }

        return response('', 204);
    }
}