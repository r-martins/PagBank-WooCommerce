jQuery(function($) {
    // Handle the click event of the "Add" button
    $("#add-box").on("click", function(e) {
        e.preventDefault();

        // Initialize the modal
        $(this).WCBackboneModal({
            template: "box-form-modal",
            target: 'tmpl-box-form-modal',
            string: {
                message: 'Add/Edit Box',
                buttons: [
                    {
                        label: 'Cancel',
                        type: 'secondary'
                    },
                    {
                        label: 'Save',
                        type: 'primary'
                    }
                ]
            }
        });

        // Handle the click event of the "Save" button in the modal
        $("#btn-ok").on("click keydown", function(e) {
            e.preventDefault();
            e.stopPropagation();

            // Get the form element
            var form = document.getElementById('box-form');

            // Check if the form is valid
            if (!form.reportValidity()) {
                // If the form is not valid, stop here
                return;
            }
            
            //disables the button to avoid repeated clicks
            $(this).prop('disabled', true);

            // Gather the form data
            var formData = $("#box-form").serialize();

            // Send the form data using an AJAX request
            $.ajax({
                type: "POST",
                url: "/wp-admin/admin-ajax.php", // Replace with your server script URL
                data: {
                    action: "pagbankConnectAddOrEditBox",
                    _wpnonce: wpApiSettings.nonce,
                    formData: formData
                },
                success: function (response) {
                    // Check if the server responded with an error
                    if (response.error) {
                        // Display the error in the modal
                        $(".modal-error").text(response.error).show();
                    } else {
                        // Close the modal
                        $(".modal-close").trigger("click");

                        // Refresh the page
                        location.reload();
                    }
                },
                error: function () {
                    var errorMessage = "Um erro ocorreu ao processar a requisição. Tente novamente.";
                    if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                        errorMessage = jqXHR.responseJSON.message;
                    }
                    $(".modal-error").text(errorMessage).show();
                },
                complete: function () {
                    //enables the button
                    $(this).prop('disabled', false);
                }
            });
        });
    });


    // Add event listener to the Edit links
    document.querySelectorAll('.wc-shipping-zone-action-edit').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();

            // Get the box data from the row
            var row = link.parentElement.parentElement.parentElement;
            var boxData = JSON.parse(row.getAttribute('data-json'));

            
            // Initialize the modal
            $(this).WCBackboneModal({
                template: "box-form-modal",
                target: 'tmpl-box-form-modal',
                string: {
                    message: 'Add/Edit Box',
                    buttons: [
                        {
                            label: 'Cancel',
                            type: 'secondary'
                        },
                        {
                            label: 'Save',
                            type: 'primary'
                        }
                    ]
                }
            });
            // Populate the modal form with the box data
            document.getElementById('box-id').value = boxData.box_id;
            document.getElementById('box-reference').value = boxData.reference;
            document.getElementById('box-width').value = boxData.outer_width;
            document.getElementById('box-length').value = boxData.outer_length;
            document.getElementById('box-height').value = boxData.outer_depth;
            document.getElementById('box-thickness').value = boxData.outer_depth - boxData.inner_depth;
            document.getElementById('box-empty_weight').value = boxData.empty_weight;
            document.getElementById('box-max_weight').value = boxData.max_weight;
            document.getElementById('box-active').checked = boxData.is_available;


            // Handle the click event of the "Save" button in the modal
            $("#btn-ok").on("click", function(e) {
                e.preventDefault();

                // Submit the form
                $("#box-form").submit();

                // Close the modal
                $(".modal-close").trigger("click");
            });
        });
    });
});