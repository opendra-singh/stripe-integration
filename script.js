jQuery(document).ready(function ($) {
	// Initialize Stripe.js using your publishable key
	const stripe = Stripe(swi_params.publishable_key);
	const client_secret = $(document).find("#woodevz-stripe-client-secret").val()
	const options = {
		clientSecret: client_secret,
		// Fully customizable with appearance API.
		appearance: {/*...*/ },
	};

	// Set up Stripe.js and Elements using the SetupIntent's client secret
	const elements = stripe.elements(options);

	// Create and mount the Payment Element
	const paymentElement = elements.create('card');
	paymentElement.mount('#woodevz-stripe-payment-element');

	$('input[name="payment_method"]').on('change', function() {
		if ($(this).val() == "woodevz_stripe") {
			$(document).find("#woodevz-stripe-payment-form").css({"display":"block"});
		}else{
			$(document).find("#woodevz-stripe-payment-form").css({"display":"none"});
		}
	})

	const form = document.getElementsByName('checkout')[0];

	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		const { paymentMethod, error } = await stripe.createPaymentMethod({
		  type: 'card',
		  card: paymentElement,
		  billing_details: {
            name: document.getElementById('billing_first_name').value + " " + document.getElementById('billing_last_name').value // Get the cardholder's name from the input field
          },
		});

		if (error) {
			// Handle payment method creation error
			console.error(error.message);
		} else {
			// PaymentMethod created successfully, pass the PaymentMethod ID to the server
			const paymentMethodId = paymentMethod.id;
			sessionStorage.setItem("woodevz_stripe_payment_method_id", paymentMethodId);
			document.cookie = "woodevz_stripe_payment_method_id="+paymentMethodId;
			// Now submit the form to the server, including the paymentMethodId
			$(document).find("#woodevz_stripe_payment_method_id").val(paymentMethodId);
			// $.post(swi_params.ajax_url, {action: "woodevz_stripe_payment_method_id", id: paymentMethodId, function(response) {
			// 	console.log(response);
			// }});
		}

	});

});