jQuery(document).ready(function($) {

   // Discussion option dropdowns
   $('ul.Options').livequery(function() {
      $(this).menu({
         showOnClick: 1,
         hide: function() {
            $(this).hide();
            this.style.visibility = 'hidden';
            // Only hide the "options" link if it's container is not class "Active"
            if (!$(this).parents('li.DiscussionRow').hasClass('Active')) {
               $(this).parents('ul.Options').hide();
            }
         }
      });
   });
   
   // Handle the various option button clicks...
   
   // 1. "Dismiss" clicks
   $('a.DismissAnnouncement').click(function() {
      var btn = this;
      var parent = $(this).parents('.Announcements');
      $.ajax({
         type: "POST",
         url: $(btn).attr('href'),
         data: 'DeliveryType=BOOL&DeliveryMethod=JSON',
         dataType: 'json',
         error: function(XMLHttpRequest, textStatus, errorThrown) {
            $.popup({}, definition('TransportError').replace('%s', textStatus));
         },
         success: function(json) {
            // Is this the last item in the announcements list?
            if ($(parent).children().length == 1) {
               // Remove the entire list
               $(parent).slideUp('fast', function() { $(this).remove(); });
               $(parent).prev().slideUp('fast', function() { $(this).remove(); });
            } else {
               // Remove the affected row
               $(btn).parents('.Announcement').slideUp('fast', function() { $(this).remove(); });
            }
         }
      });
      return false;
   });
   
   // 2. Bookmark/Unbookmark discussion
   $('a.BookmarkDiscussion').livequery('click', function() {
      var btn = this;
      var row = $(btn).parents('li.DiscussionRow');
      
      $.ajax({
         type: "POST",
         url: $(btn).attr('href'),
         data: 'DeliveryType=BOOL&DeliveryMethod=JSON',
         dataType: 'json',
         error: function(XMLHttpRequest, textStatus, errorThrown) {
            $.popup({}, definition('TransportError').replace('%s', textStatus));
         },
         success: function(json) {
            if (json.State)
               $(row).addClass('Bookmarked');
            else
               $(row).removeClass('Bookmarked');
               
            if (json.LinkText)
               $(btn).text(json.LinkText);
            
            if (json.BookmarkSum) {
               $('#Toolbar li.Bookmarks a').text(json.BookmarkSum);
               $('#Toolbar li.Bookmarks').effect('highlight', {}, 1000);
            }
         }
      });
      return false;
   });
   
   // 3. Announce discussion
   $('a.AnnounceDiscussion').livequery('click', function() {
      var btn = this;
      var row = $(btn).parents('li.DiscussionRow');
      $.ajax({
         type: "POST",
         url: $(btn).attr('href'),
         data: 'DeliveryType=BOOL&DeliveryMethod=JSON',
         dataType: 'json',
         error: function(XMLHttpRequest, textStatus, errorThrown) {
            $.popup({}, definition('TransportError').replace('%s', textStatus));
         },
         success: function(json) {
            inform(json.StatusMessage);
            if (json.RedirectUrl)
              setTimeout("document.location='" + json.RedirectUrl + "';", 300);
         }
      });
      return false;
   });
   
   // 4. Sink discussion
   $('a.SinkDiscussion').popup({
      confirm: true,
      followConfirm: false,
      afterConfirm: function(json, sender) {
         var row = $(sender).parents('li.DiscussionRow');
         if (json.State)
            $(row).addClass('Sink');
         else
            $(row).removeClass('Sink');
            
         if (json.LinkText)
            $(sender).text(json.LinkText);            
      }
   });

   // 5. Close discussion
   $('a.CloseDiscussion').popup({
      confirm: true,
      followConfirm: false,
      afterConfirm: function(json, sender) {
         var row = $(sender).parents('li.DiscussionRow');
         if (json.State)
            $(row).addClass('Close');
         else
            $(row).removeClass('Close');
            
         if (json.LinkText)
            $(sender).text(json.LinkText);            
      }
   });

   // 6. Delete discussion
   $('a.DeleteDiscussion, a.DeleteDraft').popup({
      confirm: true,
      followConfirm: false,
      deliveryType: 'BOOL', // DELIVERY_TYPE_BOOL
      afterConfirm: function(json, sender) {
         var row = $(sender).parents('li.DiscussionRow');
         if (json.ErrorMessage) {
            $.popup({}, json.ErrorMessage);
         } else {
            // Is this the last item in the list?
            if ($(row).parent().children().length == 1) {
               // Remove the entire list
               $(row).parent().slideUp('fast', function() { $(this).remove(); });
               $(row).parent().prev().slideUp('fast', function() { $(this).remove(); });
            } else {
               // Remove the affected row
               $(row).slideUp('fast', function() { $(this).remove(); });
            }
         }
      }
   });

});