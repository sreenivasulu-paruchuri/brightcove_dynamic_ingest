'use strict';

(function ($, Drupal) {
  Drupal.behaviors.uploadBcFiles = {
    attach: function (context, settings) {
      var $baseUrl = settings.path.baseUrl;
      var $form = 'form#bc-dynamic-ingest-form';

      function videoProgress($display, $progress = null) {
        var $mainId = 'brightcove-dynamic-ingest-loader';
        var $innerId = 'brightcove-dynamic-ingest-inner';
        var $progressId = 'video-progress';
        var $spinner = '<div class="overlay-spinner"><span class="spinner"></span></div>';
        var $content = '<div class="loader-content">Initiating video upload<br>Please wait...</div>';
        var $selector = $('html > body');
        if ($selector.find('#' + $mainId).length == 0) {
          $selector.append('<div style="display: none;" id="' + $mainId + '"><div id="' + $innerId + '">' + $spinner + $content + '</div></div>');
        }

        if ($selector.find('div#' + $mainId).length > 0 && $display === true) {
          $selector.css('overflow', 'hidden');
          $selector.find('#' + $mainId).show();
        }

        if ($progress) {
          var $finalMessage = ($progress == 100) ? '<br>Finalizing request' : '';
          $('#' + $mainId + ' #' + $innerId + ' .loader-content').replaceWith('<div class="loader-content">Video uploaded...<span id="' + $progressId + '">('+ $progress + '%)</span>' + $finalMessage + '</div>');
        }
      }

      function getFieldsValue($name) {
        switch ($name) {
          case 'client':
            return $($form).find("[name='api_client']:selected").val();
          case 'title':
            return $($form).find("[name='title']").val();
          case 'desc':
            return $($form).find("[name='description']").val();
          case 'video':
            return $($form).find("[name='files[video]']").get()[0].files[0];
          case 'image':
            return $($form).find("[name='thumbnail[fids]']").val();
        }
      }

      function getTags() {
        var $tags = settings.brightcoveDynamicIngest.noWatermark.tags;
        if (typeof getFieldsValue('watermark') !== 'undefined') {
          $tags = settings.brightcoveDynamicIngest.watermark.tags;
        }
        return $tags;
      }

      function getApiUrl($urltype) {
        var $accountId = settings.brightcoveDynamicIngest.noWatermark.accountId;
        if (typeof getFieldsValue('watermark') !== 'undefined') {
          $accountId = settings.brightcoveDynamicIngest.watermark.accountId;
        }
        switch ($urltype) {
          case 'cms':
            return 'https://cms.api.brightcove.com/v1/accounts/' + $accountId + '/videos/';
          case 'ingest':
            return 'https://ingest.api.brightcove.com/v1/accounts/' + $accountId + '/videos/';
        }
      }

      /**
       * @param Object
       * @returns boolean
       */
      function isJSON ($text) {
        if (typeof $text !== 'string') {
          $text = JSON.stringify($text);
        }

        try {
          JSON.parse($text);
          return true;
        } catch (e) {
          return false;
        }
      }

      function checkRequired() {
        var $check = false;
        if (getFieldsValue('title').length === 0 || typeof getFieldsValue('video') === 'undefined') {
          $check = true;
        }
        return $check;
      }

      function finalizeUpload($status, $location, $message = null) {
        $.ajax({
          url: $baseUrl + 'brightcove-dynamic-ingest/finalize-upload',
          method: 'POST',
          cache: false,
          async: false,
          data: {'status': $status, 'location': $location, 'message': $message},
          success: function ($url) {
            location.replace($url);
          },
          error: function ($error) {
            location.reload();
          }
        });
      }

      function getAuthToken() {
        var $functionName = 'getAuthToken';
        var $result = false;
        $.ajax({
          url: $baseUrl + 'brightcove-dynamic-ingest/get-token',
          method: 'POST',
          cache: false,
          async: false,
          data: {'client_name': 'default'},
          success: function (data) {
            if (isJSON(data) === false) {
              finalizeUpload('error', $functionName, data);
            }
            $result = $.parseJSON(data);
            if ($result.error) {
              finalizeUpload('error', $functionName, $result.error_description);
            }
          },
          error: function($error){
            finalizeUpload('error', $functionName, $error.responseText);
          }
        });
        return $result;
      }

      function ingestUrl($video, $uploadUrls, $fileUrl = null) {
        var $functionName = 'ingestUrl';
        var $tokens = getAuthToken();
        var $pushData = {'master': {'url': $uploadUrls.api_request_url}};
        if ($fileUrl) {
          $pushData.poster = {'url': $fileUrl};
          $pushData.thumbnail = {'url': $fileUrl};
          $pushData['capture-images'] = false;
        }
        $.ajax({
          url: getApiUrl('ingest') + $video.id + '/ingest-requests',
          method: 'POST',
          cache: false,
          async: false,
          data: JSON.stringify($pushData),
          headers: {
            'Authorization': $tokens.token_type + ' ' + $tokens.access_token,
            'Content-Type': 'application/json',
          },
          success: function (result) {
            finalizeUpload('success', $functionName);
          },
          error: function($error){
            finalizeUpload('error', $functionName, $error.responseText);
          }
        });
      }

      function getThumbnailUrl($video, $uploadUrls, $fid) {
        var $functionName = 'getThumbnailUrl';
        $.ajax({
          url: $baseUrl + 'brightcove-dynamic-ingest/get-thumbnail',
          method: 'POST',
          cache: false,
          async: false,
          data: {'fid': $fid, 'location': $functionName},
          success: function ($fileUrl) {
            ingestUrl($video, $uploadUrls, $fileUrl);
          },
          error: function ($error) {
            finalizeUpload('error', $functionName, $error.responseText);
          }
        });
      }

      function uploadVideo($video, $uploadUrls) {
        var $functionName = 'uploadVideo';
        AWS.config.update({
          accessKeyId: $uploadUrls.access_key_id,
          secretAccessKey: $uploadUrls.secret_access_key,
          sessionToken: $uploadUrls.session_token,
        });
        var upload = new AWS.S3.ManagedUpload({
          params: {
            Bucket: $uploadUrls.bucket,
            Key: $uploadUrls.object_key,
            Body: getFieldsValue('video')
          }
        });

        upload.on('httpUploadProgress', function (progress) {
          setTimeout(function(){
            videoProgress(true, parseInt((progress.loaded * 100) / progress.total));
          }, 1);
        });

        var promise = upload.promise();
        promise.then(
          function(data) {
            var $imageFid = getFieldsValue('image');
            if (typeof $imageFid !== 'undefined' && $imageFid.length > 0) {
              getThumbnailUrl($video, $uploadUrls, $imageFid);
            }
            else {
              ingestUrl($video, $uploadUrls);
            }
          },
          function($error) {
            finalizeUpload('error', $functionName, $error.message);
          }
        );
      }

      function getUploadUrls($tokens, $video) {
        var $functionName = 'getUploadUrls';
        $.ajax({
          url: getApiUrl('cms') + $video.id + '/upload-urls/' + getFieldsValue('video').name,
          method: 'GET',
          cache: false,
          async: false,
          headers: {
            'Authorization': $tokens.token_type + ' ' + $tokens.access_token,
          },
          success: function ($uploadUrls) {
            uploadVideo($video, $uploadUrls);
          },
          error: function($error){
            finalizeUpload('error', $functionName, $error.responseText);
          }
        });
      }

      function createVideo() {
        var $functionName = 'createVideo';
        var $pushData = {'name': getFieldsValue('title'), 'description': getFieldsValue('desc'), 'tags': getTags()};
        var $tokens = getAuthToken();
        $.ajax({
          url: getApiUrl('cms'),
          method: 'POST',
          cache: false,
          async: false,
          data: JSON.stringify($pushData),
          headers: {
            'Authorization': $tokens.token_type + ' ' + $tokens.access_token,
            'Content-Type': 'application/json',
          },
          success: function ($video) {
            getUploadUrls($tokens, $video);
          },
          error: function($error){
            finalizeUpload('error', $functionName, $error.responseText);
          }
        });
      }

      videoProgress(false);
      // Form submit.
      $($form).once().on('submit', function (e){
        e.preventDefault();
        if (checkRequired() === false) {
          var $fileSize = getFieldsValue('video').size;
          if ($fileSize > (1024*1024*1024*2)) {
            return alert('Video should not be greater than 2GB');
          }
          videoProgress(true);
          setTimeout(function (){
            createVideo();
          }, 2);
        }
        else {
          alert('Please make sure to fill-up all the required fields');
        }
      });
    }
  };
})(jQuery, Drupal);
