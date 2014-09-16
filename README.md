fork from https://github.com/rezzza/PaymentBe2billBundle

main modifications: approve mapped to the "authorize" method, approveAndDeposit to the "payment" method.

Example usage with an angular / symfony api app (crap code, it's for the idea)

Since be2bill does not allow sending credit card data through their API, the first step is to use their form.

    class SomeOrderController
    {
        public function postOrderAction(SomeProduct $product, $orderColor) {
            $order = new SomeOrder($product);
            $order->setColor($orderColor);
            $this->get('doctrine')->getManager()->persist($order);
            $this->get('doctrine')->getManager()->flush();

            $params = array(
                'ORDERID' => $order->getId(),
                'CREATEALIAS' => 'yes'
            );

            $data = new ExtendedData;

            $params = $this->get('payment.be2bill.client')->configureParameters($params);

            $data->set('be2bill_params', $params);

            $paymentInstruction = new PaymentInstruction($product->getAmount(), 'EUR', 'be2bill', $data);

            $this->get('payment.plugin_controller')->createPaymentInstruction(Be2billDirectLinkPlugin::PAYMENT_OPERATION, $paymentInstruction);

            $order->setPaymentInstruction($paymentInstruction);
            $this->get('doctrine')->getManager()->flush();

            return someApiResponse(201, $data);
        }
    }

the checkout page with the order form and the payment form in the same page using an iframe

    <!-- order form -->
    <form>
        <select ng-model="order.color">
            <option value="green">green</option>
            <option value="extra-green">extra-green</option>
        </select>

        <button ng-click="createOrder()">checkout</button>
    </form>

    <iframe name="be2bill-iframe"></iframe>

    <form action="{{ be2bill_endpoint }}" method="POST" id="be2bill-form" target="be2bill-iframe">
        <input ng-repeat="v,k in data" name="{{ k }}" value="{{ v }}" type="hidden"/>
    </form>

    <script>

    angular.module('app').controller(function($sce, $scope) {
        $scope.be2bill_endpoint = 'https://secure-test.be2bill.com/front/form/process';

        $scope.order = {
            product: 'some_product',
            color: 'green'
        };

        $scope.createOrder = function() {
            someApiClass.createOrder($scope.order).then(function(data) {
                if ($('#checkout-container').children().length !== 0) {
		            $('#checkout-container').children().remove();
	            }

                $('#be2bill-form').submit();
            });
        };
    });

    <script/>

the return page, since the payment form is in an iframe we change the parent location

    <html>
    <body>
    <script>
        window.parent.document.location.href = 'https://mysite.com/orders/status'
    </script>
    </body>
    </html>

the notification processing. I'm not 100% sure but I think this is processed synchronously by be2bill (eg. call the notification url, then redirect to the return url)

    class SomePaymentController
    {
        public function handleBe2billNotificationAction(Request $request)
        {
            $response = new \Pourquoi\PaymentBe2billBundle\Client\Response($request->all());

            $order = $this->getDoctrine()->getManager()->getRepository('SomeOrderRepository')->find($response->getOrderId());
            $instruction = $order->getPaymentInstruction();

            if (null === $pendingTransaction = $instruction->getPendingTransaction()) {
                $payment = $this->get('payment.plugin_controller')->createPayment($instruction->getId(), $instruction->getAmount() - $instruction->getDepositedAmount());
            } else {
                $payment = $pendingTransaction->getPayment();
            }

            $this->get('payment.plugin.be2bill')->setFormResponse($response);

            switch( $response->getOperationType() ) {
                case Be2billDirectLinkPlugin::PAYMENT_OPERATION:
                    $result = $this->get('payment.plugin_controller')->approveAndDeposit($payment->getId(), $payment->getTargetAmount());
                    if( Result::STATUS_SUCCESS !== $result->getStatus() ) {
                        throw new \RuntimeException('Transaction was not successful: '.$result->getReasonCode());
                    }

                    // either do something with the validated order or listen to the payment status change event
                    // the alias is saved in the transaction table, maybe save replicate it in the user table
                    $order->setValid();
                    $this->getDoctrine()->getManager()->flush();

                    return new Response('OK');
                    break;

               ... handle other operations
            }
        }
    }
