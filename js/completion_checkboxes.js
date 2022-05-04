(function ($, Drupal, drupalSettings) {
  'use strict';

  let CompletionCheckboxes = Drupal.behaviors.completionCheckboxes = {

    data: {
      CSRF: false,
      basePath: location.protocol + '//' + location.hostname + drupalSettings.path.baseUrl,
    },

    attach: function (context, settings) {
      CompletionCheckboxes.requestCSRF();

      $('.c-collectionItem__checkbox', context).once('checkbox_completion_behaviors').each(function () {
        const $checkbox = $(this);
        CompletionCheckboxes.clickHandler($checkbox);
      })
    },


    requestCSRF: function () {
      // Since this gets hit for each checkbox but
      // we only need to determine the token once.
      if (CompletionCheckboxes.data.CSRF) {
        return;
      }

      console.log(drupalSettings)


      fetch(CompletionCheckboxes.data.basePath + 'rest/session/token')
        .then((response) => response.text())
        .then((csrf_token) => {
          CompletionCheckboxes.data.CSRF = csrf_token;
        });
    },

    clickHandler: function ($checkbox) {

      const collectionItemPid = $checkbox.attr('data-pid');
      const relatedNodeId = $checkbox.attr('data-nid');
      const progressItemId = $checkbox.attr('data-item-progress-id');

      $checkbox.on('click', function () {
        // Snake case for props since the data is consumed as PHP
        let checkboxData = {
          collection_item_pid: collectionItemPid,
          related_node_id: relatedNodeId,
          progress_item_id: progressItemId,
          target_completion_state: $checkbox.is(':checked')
        }

        // @todo: fail this if request fails and uncheck the box
        CompletionCheckboxes.completionUpdateHandler(checkboxData);
      });

    },

    completionUpdateHandler: function (postData) {
      let postHeaders = new Headers({
        'Content-Type': 'application/json',
        'X-CSRF-Token': CompletionCheckboxes.data.CSRF
      });

      // fetch(drupalSettings.path.baseUrl + '/progress-tracking/completion', {
      fetch(CompletionCheckboxes.data.basePath + 'progress-tracking/completion', {
        method: 'POST',
        headers: postHeaders,
        body: JSON.stringify(postData)
      })
        .then((response) => response.json())
        .then((result) => console.log(result));
    },


    // initVideoData: function () {
    //   ProgressTracking.data.duration = ProgressTracking.data.duration ||
    // ProgressTracking.video.duration; ProgressTracking.data.interval =
    // ProgressTracking.data.interval || ProgressTracking.video.duration / 100;
    // ProgressTracking.data.doPOST = ProgressTracking.data.doPOST || true; },

    // timeUpdateHandler: function (event) {
    //   ProgressTracking.initVideoData();
    //
    //   let update_interval = ProgressTracking.data.interval;
    //   let current_time = event.target.currentTime;
    //
    //   let current_time_progress = Math.round(current_time / update_interval);
    //   let current_time_modulus = current_time % update_interval;
    //
    //   /**
    //    * Note that this was refactored to use guard clauses.
    //    * Can likely be simplified further.
    //    */
    //
    //   // Only POST every 1 percent, of video duration, within +/-1 second
    //   if (!(current_time_modulus < 1 || current_time_modulus > update_interval - 1)) {
    //     ProgressTracking.data.doPOST = true;
    //   }
    //
    //   // POST only once within this +/- 1 second range
    //   if (!ProgressTracking.data.doPOST || !ProgressTracking.data.CSRF) {
    //     return;
    //   }
    //
    //   let previous_progress = ProgressTracking.data.progress;
    //
    //   // POST only when progress percent is 1 + previous progress percent
    //   if (current_time_progress == previous_progress + 1 ||
    // previous_progress == false) { console.log(time_progress); let
    // post_headers = new Headers({ 'Content-Type': 'application/json',
    //       'X-CSRF-Token': ProgressTracking.data.CSRF
    //     });
    //
    //     let post_data = {
    //       nid: drupalSettings.nid,
    //       playhead: event.target.currentTime,
    //       viewing_type: drupalSettings.viewing_type
    //     };
    //
    //     if(drupalSettings.viewing_type == 'collection'
    // )
    //   {
    //     post_data.collection_id = drupalSettings.collection_id;
    //     post_data.paragraph_id = drupalSettings.paragraph_id;
    //   }
    //
    //   fetch(drupalSettings.path.baseUrl + 'progress-tracking', {
    //     method: 'POST',
    //     headers: post_headers,
    //     body: JSON.stringify(post_data)
    //   })
    //     .then((response) => response.json())
    //     .then((result) => console.log(result));
    // }
    //
    //   ProgressTracking.data.doPOST = false;
    //   ProgressTracking.data.progress = current_time_progress;
    // },

  };

}(jQuery, Drupal, drupalSettings));
