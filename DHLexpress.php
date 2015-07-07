<?
/**
 * dhl interface
 */




//引入国家代码文件
require_once(dirname(__FILE__).'/conf/dhl-country-code.php');
$dhl = $config['dhl'];

class DhlExpress
{
	private $siteID;
	private $sitePassword;
	private $shipperAccountNumber;
	private $endpoint ;
	protected $_mode = 'staging';
	private $_stagingUrl = 'https://xmlpitest-ea.dhl.com/XMLShippingServlet';
	private $_productionUrl = 'https://xmlpi-ea.dhl.com/XMLShippingServlet';

	/**
	 * 追踪运单状态，返回的详情等级
	 * LAST_CHECK_POINT_ONLY 返回运单当前状态信息
	 * ALL_CHECK_POINTS 返回运单所有的状态信息
	 */
	const LEVEL_OF_DETAILS =  'ALL_CHECK_POINTS';

	public function __construct($mode = 'staging'){
		global $dhl;
		$this->siteID = $dhl['id'];//测试账prasanta;
		$this->sitePassword = $dhl['pass'];//测试账号密码prasanta
		$this->shipperAccountNumber = $dhl['shipperAccountNumber'];
		$this->endpoint = $mode == 'staging' ? $this->_stagingUrl : $this->_productionUrl;
	}

  /**
   * 获取请求头信息
   */
	function getServiceHeader(){
		return '<Request>
				  <ServiceHeader>
				   <MessageTime>'.date(DATE_ATOM, time()).'</MessageTime>
				   <MessageReference>1234567890123456789012345678901</MessageReference>
				   <SiteID>'.$this->siteID.'</SiteID>
				   <Password>'.$this->sitePassword.'</Password>
				  </ServiceHeader>
				 </Request>
				 <LanguageCode>en</LanguageCode>';
	}

	/**
	 * dhl 下单接口
	 * @param 订单信息
	 * 
	 */
	public function shipment($order, $plus=array())
	{
		//国家代码
		global $dhlCountryCodeArray;
    $requestBody = $this->getRequestBody($order);
		return $this->sendHttpRequest($requestBody);
	}

	/**
	 * dhl 运单追踪
	 * @param $AWBNumber = '3196099325'
	 * 
	 */
	public function tracking($AWBNumber)
	{
	   if(strlen($AWBNumber) != 10){
			return array('success'=>false,'msg'=>'订单号错误');
		}
		$trackingRequestBody = '<?xml version="1.0" encoding="UTF-8"?>
							<req:KnownTrackingRequest xmlns:req="http://www.dhl.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.dhl.com KnownTrackingRequest.xsd">
							 '.$this->getServiceHeader().'
							 <AWBNumber>'.$AWBNumber.'</AWBNumber>
							 <LevelOfDetails>ALL_CHECK_POINTS</LevelOfDetails>
							 <PiecesEnabled>B</PiecesEnabled>
							</req:KnownTrackingRequest>';
		return $this->sendHttpRequest($trackingRequestBody);
	}

	protected function getRequestBody($order){
		global $dhlCountryCodeArray;
		$pieceTag = '';
		foreach($order['piece'] as $k=>$pieceValue)
		{
			//判断重量,重量为0设为0.11KG
			$pieceTag .= "<Piece><Weight>".Common::formatNumber($pieceValue['weight'])."</Weight></Piece>";
			// 			$piece.= "<Depth>2</Depth>";
			// 			$piece.= "<Width>2</Width>";
			// 			$piece.= "<Height>2</Height>";
			$totalWeight += $pieceValue['weight'];
		}
		$addressLine = array_filter(array($order['address']['street1'],$order['address']['street2'],$order['address']['street3']));
		foreach ($addressLine as $address) {
			$consAddressLine .= '<AddressLine>'.$address.'</AddressLine>';
		}

		$str = '<?xml version="1.0" encoding="UTF-8"?>
					<req:ShipmentValidateRequestAP xmlns:req="http://www.dhl.com" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.dhl.com ship-val-req_AP.xsd">
					 '.$this->getServiceHeader().'
					 <PiecesEnabled>Y</PiecesEnabled>
					 <Billing>
					  <ShipperAccountNumber>'.$this->shipperAccountNumber.'</ShipperAccountNumber>
					  <ShippingPaymentType>S</ShippingPaymentType>
					  <DutyPaymentType>R</DutyPaymentType>
					 </Billing>
					 <Consignee>
					  <CompanyName>string</CompanyName>
					  '.$consAddressLine.'
					  <City>'.$order['address']['city'].'</City>
					  <CountryCode>'.$order['shippingCountryCode'].'</CountryCode>
					  <CountryName>'.$dhlCountryCodeArray[$order['shippingCountryCode']].'</CountryName>
					  <Contact>
					   <PersonName>'.$order['address']['receiver'].'</PersonName>
					   <PhoneNumber>'.$order['address']['telephone'].'</PhoneNumber>
					  </Contact>
					 </Consignee>
					 <Commodity>
					  <CommodityCode>1</CommodityCode>
					  <CommodityName>String</CommodityName>
					 </Commodity>
					 <Dutiable>
					  <DeclaredValue>100</DeclaredValue>
					  <DeclaredCurrency>USD</DeclaredCurrency>
					  <ShipperEIN>Text</ShipperEIN>
					 </Dutiable>
					 <Reference>
					  <ReferenceID>'.$order['biaojuCode'].'</ReferenceID>
					  <ReferenceType>St</ReferenceType>
					 </Reference>
					 <ShipmentDetails>
					  <NumberOfPieces>'.count($order['piece']).'</NumberOfPieces>
					  <CurrencyCode>CNY</CurrencyCode>
					  <Pieces>
					   '.$pieceTag.'
					  </Pieces>
					  <PackageType>OD</PackageType>
					  <Weight>'.Common::formatNumber($totalWeight).'</Weight>
					  <DimensionUnit>C</DimensionUnit>
					  <WeightUnit>K</WeightUnit>
					  <GlobalProductCode>P</GlobalProductCode>
					  <LocalProductCode>P</LocalProductCode>
					  <DoorTo>DD</DoorTo>
					  <Date>'.date('Y-m-d').'</Date>
					  <Contents>For testing purpose only. Please do not ship</Contents>
					  <InsuredAmount>120</InsuredAmount>
					 </ShipmentDetails>
					 <Shipper>
					  <ShipperID>600000000</ShipperID>
					  <CompanyName>Santa inc</CompanyName>
					  <AddressLine>333 Twin</AddressLine>
					  <City>Beijing</City>
					  <PostalCode>100176</PostalCode>
					  <CountryCode>CN</CountryCode>
					  <CountryName>China</CountryName>
					  <FederalTaxId>SFTD10222124893</FederalTaxId>
					  <Contact>
					   <PersonName>santa santa</PersonName>
					   <PhoneNumber>153000000</PhoneNumber>
					  </Contact>
					 </Shipper>
					 <LabelImageFormat>PDF</LabelImageFormat>
					 <Label>
					  <LabelTemplate>8X4_A4_PDF</LabelTemplate>
					 </Label>
					</req:ShipmentValidateRequestAP>';
		/* $shipper = '<Shipper>
					  <ShipperID>600000000</ShipperID>
					  <CompanyName>santa inc</CompanyName>
					  <AddressLine>'.$order['shipperAddress']->address.'</AddressLine>
					  <City>'.$order['shipperAddress']->city.'</City>
					  <PostalCode>'.$order['shipperAddress']->zipcode.'</PostalCode>
					  <CountryCode>CN</CountryCode>
					  <CountryName>China</CountryName>
					  <FederalTaxId>SFTD10222124893</FederalTaxId>
					  <Contact>
					   <PersonName>'.$order['shipperAddress']->contact.'</PersonName>
					   <PhoneNumber>'.$order['shipperAddress']->mobile ? $order['shipperAddress']->mobile : $order['shipperAddress']->telephone.'</PhoneNumber>
					  </Contact>
				 </Shipper>'; */

		return $str;
	}

	protected function sendHttpRequest($requestBody)
	{
		if (!$ch = curl_init())
		{
			throw new \Exception('could not initialize curl');
		}

		curl_setopt($ch, CURLOPT_URL, $this->endpoint);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_PORT , 443);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
		$result = curl_exec($ch);

		if (curl_error($ch))
		{
			print_r(curl_error($ch));
			return false;
		}
		else
		{
			curl_close($ch);
		}

		return $result;
	}
}
?>
