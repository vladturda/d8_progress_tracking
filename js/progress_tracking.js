(function ($, Drupal, drupalSettings) {
  'use strict';

  let ProgressTracking = Drupal.behaviors.ProgressTracking = {
    video: false,
    data: {
      CSRF: false,
      doPOST: false,
      active: false,
      duration: false,
      interval: false,
      progress: false
    },

    initialize: function () {
      console.log('initd')
      ProgressTracking.requestCSRF();

      flowplayer.cloud.then(function () {
        let video = ProgressTracking.video = flowplayer('#async_flowplayer');

        // Does not reliably fire before other events.
        // Don't use it.
        // video.addEventListener('loadeddata', (event) => {
        //   ProgressTracking.data.duration = video.duration;
        //   ProgressTracking.data.interval = video.duration / 100;
        //   ProgressTracking.data.POST = true;
        // });

        // Deactivate POST progress updates while paused or seeking
        video.addEventListener('pause', (event) => {
          ProgressTracking.data.active = false;
        });
        video.addEventListener('seeking', (event) => {
          ProgressTracking.data.active = false;
        });

        // Activate POST progress update while video is playing
        video.addEventListener('playing', (event) => {
          ProgressTracking.data.active = true;
        });

        video.addEventListener('timeupdate', (event) => {
          if (ProgressTracking.data.active) {
            ProgressTracking.timeUpdateHandler(event);
          }
        });

      });
    },

    requestCSRF: function () {
      console.log('csrf')
      fetch(drupalSettings.path.baseUrl + 'rest/session/token')
        .then((response) => response.text())
        .then((csrf_token) => {
          ProgressTracking.data.CSRF = csrf_token;
        });
    },

    initVideoData: function () {
      console.log('init video data')
      ProgressTracking.data.duration = ProgressTracking.data.duration || ProgressTracking.video.duration;
      ProgressTracking.data.interval = ProgressTracking.data.interval || ProgressTracking.video.duration / 100;
      ProgressTracking.data.doPOST = ProgressTracking.data.doPOST || true;
    },

    timeUpdateHandler: function (event) {
      console.log('time update handler hit')

      ProgressTracking.initVideoData();

      let update_interval = ProgressTracking.data.interval;
      let current_time = event.target.currentTime;

      let current_time_progress = Math.round(current_time / update_interval);
      let current_time_modulus = current_time % update_interval;

      /**
       * Note that this was refactored to use guard clauses.
       * Can likely be simplified further.
       */

      // Only POST every 1 percent, of video duration, within +/-1 second
      if (!(current_time_modulus < 1 || current_time_modulus > update_interval - 1)) {
        ProgressTracking.data.doPOST = true;
      }

      // POST only once within this +/- 1 second range
      if (!ProgressTracking.data.doPOST || !ProgressTracking.data.CSRF) {
        return;
      }

      let previous_progress = ProgressTracking.data.progress;

      // POST only when progress percent is 1 + previous progress percent
      if (current_time_progress == previous_progress + 1 || previous_progress == false) {
        //console.log(time_progress);
        let post_headers = new Headers({
          'Content-Type': 'application/json',
          'X-CSRF-Token': ProgressTracking.data.CSRF
        });

        let post_data = {
          nid: drupalSettings.nid,
          playhead: event.target.currentTime,
          viewing_type: drupalSettings.viewing_type
        };

        if (drupalSettings.viewing_type == 'collection') {
          post_data.collection_id = drupalSettings.collection_id;
          post_data.collection_item_paragraph_id = drupalSettings.collection_item_paragraph_id;
        }

        fetch(drupalSettings.path.baseUrl + 'progress-tracking', {
          method: 'POST',
          headers: post_headers,
          body: JSON.stringify(post_data)
        })
          .then((response) => response.json())
          .then((result) => console.log(result));
      }

      ProgressTracking.data.doPOST = false;
      ProgressTracking.data.progress = current_time_progress;
    },

    attach: function (context, settings) {
      console.log('attach')
      $('#async_flowplayer', context).once('progress_tracking').each(ProgressTracking.initialize);
    }
  };

}(jQuery, Drupal, drupalSettings));
