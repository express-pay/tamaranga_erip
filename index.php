<?php

class Plugin_Expresspay_erip_p1700ce extends Plugin
{
    public function init()
    {
        parent::init();

        $this->setSettings(array(
            'extension_id'   => 'p1700ce8f593491d2351ede8a29be49353881900',
            'plugin_title'   => 'Экспресс Платежи: ЕРИП',
            'plugin_version' => '1.0.0',
        ));

        /**
         * Настройки заполняемые в админ. панели
         */
        $this->configSettings(array(
            'isTest' => array(
                'title' => 'Тестовый режим',
                'description' => 'Использовать тестовый сервер для выставления счетов',
                'input' => 'checkbox',
            ),
            'serviceId' => array(
                'title' => 'Номер услуги',
                'required' => true,
                'input' => 'text',
                'description' => 'Можно узнать в личном кабинете сервиса "Экспресс Платежи" в настройках услуги.',
            ),
            'token' => array(
                'title' => 'Токен',
                'required' => true,
                'input' => 'text',
                'description' => 'Можно узнать в личном кабинете сервиса "Экспресс Платежи" в настройках услуги.',
            ),
            'useSignature' => array(
                'title' => 'Использовать цифровую подпись для выставления счетов',
                'description' => 'Значение должно совпадать со значением, установленным в личном кабинете сервиса "Экспресс Платежи".',
                'input' => 'checkbox',
            ),
            'secretWord' => array(
                'title' => 'Секретное слово',
                'input' => 'text',
                'description' => 'Задается в личном кабинете, секретное слово должно совпадать с секретным словом, установленным в личном кабинете сервиса "Экспресс Платежи".',
            ),
            'notifUrl' => array(
                'title' => 'Адрес для получения уведомлений',
                'input' => 'text',
                'default' => Bills::url('process', array('ps'=>$this->key())),
            ),
            'useSignatureForNotif' => array(
                'title' => 'Использовать цифровую подпись для уведомлений',
                'description' => 'Значение должно совпадать со значением, установленным в личном кабинете сервиса "Экспресс Платежи".',
                'input' => 'checkbox',
            ),
            'secretWordForNotif' => array(
                'title' => 'Секретное слово для уведомлений',
                'input' => 'text',
                'description' => 'Задается в личном кабинете, секретное слово должно совпадать с секретным словом, установленным в личном кабинете сервиса "Экспресс Платежи".',
            ),
            'showQrCode' => array(
                'title' => 'Показывать Qr-код',
                'input' => 'checkbox',
            ),
            'pathToErip' => array(
                'title' => 'Путь по ветке ЕРИП',
                'input' => 'textarea',
            ),
            'isNameEdit' => array(
                'title' => 'Разрешено изменять ФИО',
                'input' => 'checkbox',
            ),
            'isAmountEdit' => array(
                'title' => 'Разрешено изменять сумму',
                'input' => 'checkbox',
            ),
            'isAddressEdit' => array(
                'title' => 'Разрешено изменять адрес',
                'input' => 'checkbox',
            ),
        ));
    }

    protected function start()
    {
        // Код плагина
         # Дополняем список доступных пользователю способов оплаты
         bff::hooks()->billsPaySystemsUser(array($this, 'user_list'));

         # Дополняем данными о системе оплаты
         bff::hooks()->billsPaySystemsData(array($this, 'system_list'));
 
         # Форма выставленного счета, отправляемая системе оплаты
         bff::hooks()->billsPayForm(array($this, 'form'));

        # Форма обработки страницы успешной оплаты
        bff::hooks()->billsPaySuccess(array($this, 'success'));

        # Форма обработки страницы ошибки оплаты
        bff::hooks()->billsPayFail(array($this, 'fail'));
 
         # Обработка запроса от системы оплаты
        bff::hooks()->billsPayProcess(array($this, 'process'));
    }

    public function success()
    {
        $output =
				'<table style="width: 100%;text-align: left;">
            <tbody>
                    <tr>
                        <td valign="top" style="text-align:left;">
                        <h3>Ваш номер счёта: ##ORDER_ID##</h3>
                            Вам необходимо произвести платеж в любой системе, позволяющей проводить оплату через ЕРИП (пункты банковского обслуживания, банкоматы, платежные терминалы, системы интернет-банкинга, клиент-банкинга и т.п.).
                            <br />
                            <br />1. Для этого в перечне услуг ЕРИП перейдите в раздел:  <b>##ERIP_PATH##</b> <br />
                            <br />2. В поле <b>"Номер счёта"</b> введите <b>##ORDER_ID##</b> и нажмите "Продолжить" <br />
                            <br />3. Указать сумму для оплаты<br />
                            <br />4. Совершить платеж.<br />
                        </td>
                            <td style="text-align: center;padding: 70px 20px 0 0;vertical-align: middle">
								##OR_CODE##
								<p><b>##OR_CODE_DESCRIPTION##</b></p>
								</td>
						</tr>
				</tbody>
            </table>
            <br />';

			$output = str_replace('##ORDER_ID##', $_REQUEST['ExpressPayAccountNumber'],  $output);
            $output = str_replace('##ERIP_PATH##', $this->config('pathToErip'),  $output);
            
            $request_params_for_qr = array(
                "Token" => $this->config('token'),
                "InvoiceId" => $_REQUEST['ExpressPayInvoiceNo'],
                'ViewType' => 'base64'
            );
            
            $request_params_for_qr["Signature"] = $this->compute_signature($request_params_for_qr, $this->config('token'), $this->config('secretWord'), 'get_qr_code');
			if ($this->config('showQrCode')) {
                if ($this->config('isTest'))
				{
					$url = 'https://sandbox-api.express-pay.by/v1/qrcode/getqrcode/?';
				}
				else
				{
					$url = 'https://api.express-pay.by/v1/qrcode/getqrcode/?';
                }
                $request_params_for_qr  = http_build_query($request_params_for_qr);
                $response_qr = file_get_contents($url.$request_params_for_qr);
                $response_qr = json_decode($response_qr);
				$output = str_replace('##OR_CODE##', '<img src="data:image/jpeg;base64,' . $response_qr->QrCodeBody . '"  width="200" height="200"/>',  $output);
				$output = str_replace('##OR_CODE_DESCRIPTION##', 'Отсканируйте QR-код для оплаты',  $output);
			} else {
				$output = str_replace('##OR_CODE##', '',  $output);
				$output = str_replace('##OR_CODE_DESCRIPTION##', '',  $output);
			}

			$result = $output;

			return $result;
    }

    public function fail()
    {
        $output_error =
        '<br />
        <h3>Ваш номер заказа: ##ORDER_ID##</h3>
        <p>При выполнении запроса произошла непредвиденная ошибка. Пожалуйста, повторите запрос позже или обратитесь в службу технической поддержки магазина</p>';

        $output_error = str_replace('##ORDER_ID##', $_REQUEST['ExpressPayAccountNumber'],  $output_error);

        $result = $output_error;

        return $result;
    }

    protected function id()
    {
        return 201;
    }

    protected function key()
    {
        return 'expresspay_erip';
    }

    /**
     * Дополняем список доступных пользователю способов оплаты
     * @param array $list список систем оплат
     * @param array $extra: 'logoUrl', 'balanceUse'
     * @return array
     */
    public function user_list($list, $extra)
    {
        $list['expresspay_erip_plugin'] = array(
            'id'           => $this->id(),
            'logo_desktop' => $this->url('/logo.png'),
            'logo_phone'   => $this->url('/logo.png'),
            'way'          => '',
            'title'        => 'Экспресс Платежи: ЕРИП', # Название способа оплаты
            'currency_id'  => 2, # Рубли (ID валюты в системе)
            'enabled'      => true, # Способ доступен пользователю
            'priority'     => 0, # Порядок: 0 - последняя, 1+
        );

        return $list;
    }

    /**
     * Дополняем данными о системе оплаты
     * @param array $list
     * @return array
     */
    public function system_list($list)
    {
        $list[$this->id()] = array(
            'id'    => $this->id(),
            'key'   => $this->key(),
            # Название системы для описания счета в админ. панели
            'title' => 'Экспресс Платежи: ЕРИП',
            'desc'  => '',
        );
        return $list;
    }

    /**
     * Форма выставленного счета, отправляемая системе оплаты
     * @param string $form HTML форма
     * @param integer $paySystem ID системы оплаты для которой необходимо сформировать форму
     * @param array $data дополнительные данные о выставляемом счете:
     *  amount - сумма для оплаты
     *  bill_id - ID счета
     *  bill_description - описание счета
     *  bill_data = [currency_id]
     * @return string HTML
     */
    public function form($form, $paySystem, $data)
    {
        if ($paySystem != $this->id()) {
            return $form;
        }

        $fields =  array(
            'ServiceId'         => $this->config('serviceId'),
            'AccountNo'         => $data['bill_id'],
            'Amount'            => number_format(floatval($data['amount']), 2, ',', ''),
            'Currency'          => 933,
            'ReturnType'        => 'redirect',
            'ReturnUrl'         => Bills::url('success'),
            'FailUrl'           => Bills::url('fail'),
            'Expiration'        => '',
            'Info'              => $data['bill_description'],
            'Surname'           => '',
            'FirstName'         => '',
            'Patronymic'        => '',
            'Street'            => '',
            'House'             => '',
            'Apartment'         => '',
            'IsNameEditable'    => $this->config('isNameEdit'),
            'IsAddressEditable' => $this->config('isAddressEdit'),
            'IsAmountEditable'  => $this->config('isAmountEdit'),
            'EmailNotification' => '',
            'SmsPhone'          => ''
        );

        # Подписываем
        $fields['Signature'] = $this->compute_signature($fields, $this->config('token'), $this->config('secretWord'));


        $baseUrl = "https://api.express-pay.by/v1/";
		
		if($this->config('isTest'))
			$baseUrl = "https://sandbox-api.express-pay.by/v1/";
		
		$url = $baseUrl . "web_invoices";

        $form = '<form id="expressPayForm" style="display:none;" method="POST" action="'.$url.'">';
        foreach ($fields as $key => $val) {
            if (is_array($val)) {
               foreach ($val as $value) {
                    $form .= '<input type="hidden" name="'.$key.'" value="'.htmlspecialchars($value).'" />';
               }
            } else {
               $form .= '<input type="hidden" name="'.$key.'" value="'.htmlspecialchars($val).'" />';
            }
        }
        $form .= '</form>';
        return $form;
    }

    /**
     * Обработка запроса от системы оплаты
     * Метод должен завершать поток путем вызова bff::shutdown();
     * @param string $system ключ обрабываемой системы оплаты
     */
    public function process($system)
    {
        if ($system != $this->key()) return;

        $json = $_POST['Data'];
        $data = json_decode($json);
        $signature = $_POST['Signature'];

        if ($this->config('useSignatureForNotif') && $signature == $this->computeSignature($json, $this->config('secretWordForNotif')))
        {
            if($data->CmdType == '3' && $data->Status == '3' || '6')
            {
                # Обрабатываем счет
                $this->bills()->processBill($data->AccountNo, $data->Amount, $this->id());
                $status = 'OK | payment received';
			    echo($status);
			    header("HTTP/1.0 200 OK");
                bff::shutdown();
            }
        }
        else if (!isset($signature))
        {
            if($data->CmdType == '3' && $data->Status == '3' || '6')
            {
                # Обрабатываем счет
                $this->bills()->processBill($data->AccountNo, $data->Amount, $this->id());
                $status = 'OK | payment received';
			    echo($status);
			    header("HTTP/1.0 200 OK");
                bff::shutdown();
            }

        }
        else
        {
            $status = 'FAILED | wrong notify signature'; 
            echo($status);
            header("HTTP/1.0 400 Bad Request");
            bff::shutdown();
        }
    }

    protected function computeSignature($json, $secretWord)
    {
    $hash = NULL;
    
	$secretWord = trim($secretWord);
	
    if (empty($secretWord))
		$hash = strtoupper(hash_hmac('sha1', $json, ""));
    else
        $hash = strtoupper(hash_hmac('sha1', $json, $secretWord));
    return $hash;
    }

    protected function compute_signature($request_params, $token, $secret_word, $method = 'add_invoice')
    {
	$secret_word = trim($secret_word);
	$normalized_params = array_change_key_case($request_params, CASE_LOWER);
	$api_method = array( 
		'add_invoice' => array(
							"serviceid",
							"accountno",
							"amount",
							"currency",
							"expiration",
							"info",
							"surname",
							"firstname",
							"patronymic",
							"city",
							"street",
							"house",
							"building",
							"apartment",
							"isnameeditable",
							"isaddresseditable",
							"isamounteditable",
							"emailnotification",
							"smsphone",
							"returntype",
							"returnurl",
							"failurl"),
		'get_qr_code' => array(
							"invoiceid",
							"viewtype",
							"imagewidth",
							"imageheight"),
		'add_invoice_return' => array(
							"accountno",
							"invoiceno"
		)
	);

	$result = $token;
    
	foreach ($api_method[$method] as $item)
		$result .= ( isset($normalized_params[$item]) ) ? $normalized_params[$item] : '';
	$hash = strtoupper(hash_hmac('sha1', $result, $secret_word));

	return $hash;
    }

}