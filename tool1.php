<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LIXILパーツショップからスクレイピングしてEXCELファイル作成</title>   
    <style rel="stylesheet" type="text/css">
        #form1 {
            width: 800px;
            height: 200px;            
            margin: 0 auto;
        }
        .subtitle {
            width: 500px;
            margin: 100px auto 10px;
        }
        input[type="text"] {
            display: block;
            width: 500px;
            margin: 0 auto 30px;
        }
        input[type="submit"] {
            display: block;
            width: 150px;
            height: 30px;
            margin: 0 auto;          
        }
        .result {
            width: 500px;
            margin: 20px auto;
        }
    </style>
</head>
<body>

    <?php
    /*
        if(isset($_GET['button'])){
            header("Location: {$_SERVER['PHP_SELF']}");
            exit;
        }
    */
    ?>

    <form id="form1" method="get">
        <div class="subtitle"><p>LIXILパーツショップURL：</p></div>
        <input type="text" name="url" maxlength="300" required><br>
        <input type="submit" name="button" value="Excelファイル作成"/>
    </form>

    <script type="text/javascript"  src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script></script>

    <?php      
        
        // UserAgent
        define("USER_AGENT_TEXT", "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36");
        
        
        /* cURL  */            
        // APIを呼び出し、結果を受け取る処理（URLにアクセスしその結果を取得する処理）
        // $url         ：APIの URI（アクセスする URL）
        // $responseType：受け取る結果のタイプ（header、html、json）
        function getApiDataCurl($url, $responseType = "html" ){
        
            if ( $responseType == "header" ) {
                $option = [
                        CURLOPT_RETURNTRANSFER => true,   // 文字列として返す
                        CURLOPT_TIMEOUT        => 3000,   // タイムアウト時間
                        CURLOPT_HEADER         => true,
                    CURLOPT_NOBODY         => true,
                    CURLOPT_SSL_VERIFYPEER => false,  // サーバ証明書の検証をしない
                ];
            } else {
                $option = [
                    CURLOPT_RETURNTRANSFER => true,   // 文字列として返す
                    CURLOPT_TIMEOUT        => 3000,   // タイムアウト時間
                    CURLOPT_SSL_VERIFYPEER => false,  // サーバ証明書の検証をしない
                    CURLOPT_USERAGENT      => USER_AGENT_TEXT,  // UserAgentを指定
                ];
            }
        
            $ch = curl_init($url);
            curl_setopt_array($ch, $option);
            
            $body     = curl_exec($ch);
            $info     = curl_getinfo($ch);
            $errorNo  = curl_errno($ch);
            $errorMsg = curl_error($ch);
            
            // 「CURLE_OK」以外はエラーなのでエラー情報を返す
            if ($errorNo !== CURLE_OK) {
                // 詳しくエラーハンドリングしたい場合はerrorNoで確認
                // タイムアウトの場合はCURLE_OPERATION_TIMEDOUT
                return $errorNo . " : " . $errorMsg;
            }
        
            // 200以外のステータスコードは失敗なのでそのステータスコードを返す
            if ($info['http_code'] !== 200) {
                return $info['http_code'];
            }
        
            // headerのみ取得
            if($responseType == "header") {
                $responseArray = explode("\n", $body);                   // 行に分割
                $responseArray = array_map('trim', $responseArray);      // 各行にtrim()をかける
                $responseArray = array_filter($responseArray, 'strlen'); // 文字数が0の行を取り除く
                $responseArray = array_values($responseArray);           // キーを連番に振りなおす
            
            // HTMLの本体を取得
            } elseif($responseType == "html"){
                $responseArray = $body;
            
            // JSONで取得した情報を配列に変換して取得
            } else {
                $responseArray = json_decode($body, true);               // JSON を配列に変換
            }
        
            return $responseArray;
        }


        // PHP Simple HTML DOM Parser パッケージ読込
        require_once "../simple_html_dom.php";
       
        //phpspreadsheetパッケージ読込        
        require_once "../vendor/autoload.php";
        
        use PhpOffice\PhpSpreadsheet\Writer\Xlsx as Writer;
        
        //「Excelファイル作成」ボタン押下
        if(isset($_GET['button'])){

            //スクレイピング
            $url = $_GET['url'];
            $htmlSource = getApiDataCurl($url,"html");
            $html = str_get_html( $htmlSource );
            

            $ItemNum = array();
            $ItemName = array();
            $ItemPrice = array();
            $ItemExp = array();
            $ItemImg = array();
            
                     
            //ページからテキスト抽出する関数
            function Scraping($aclass){               
                global $html;
                $ItemText = array();

                foreach($html->find("p[class=$aclass]") as $value ){
                    array_push($ItemText,$value);
                }

                for ($i = 0 ; $i < count($ItemText); $i++){
                    $ItemText[$i] = $ItemText[$i]->plaintext;
                    $ItemText[$i] = str_replace(array(" ", "　","	","商品管理番号:","円(税込)"), "", $ItemText[$i]);
                } 

                return $ItemText;
            }

            $ItemNum = Scraping("un_product_list_item_nmb");
            $ItemName = Scraping("un_product_list_item_name");
            $ItemPrice = Scraping("un_product_list_item_price");
            $ItemExp = Scraping("un_product_list_item_txt hp_fcBlue"); 
            
            foreach($html->find("img[id=goodsGroupImageThumbnail]") as $value ){
                array_push($ItemImg,$value);
            }
            for ($i = 0 ; $i < count($ItemImg); $i++){
                $ItemImg[$i] = $ItemImg[$i]->src;
                $ItemImg[$i] = str_replace(array(" ", "　","	"), "", $ItemImg[$i]);
            } 
            
            //Excel書込
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $sheet = $spreadsheet->getActiveSheet(); 
            $sheet->getCell('A1')->setValue('商品管理番号');
            $sheet->getCell('B1')->setValue('商品名');
            $sheet->getCell('C1')->setValue('価格(税込)');
            $sheet->getCell('D1')->setValue('商品説明');
            $sheet->getCell('E1')->setValue('商品画像URL');     
         
            //EXcel書込する関数
            function ExcelWrite($col,$ItemText){
                global $sheet;

                for($i = 0 ; $i < count($ItemText); $i++){                                               
                    $sheet->getCell($col.($i + 2))->setValue($ItemText[$i]);
                } 
            }

            ExcelWrite("A",$ItemNum);
            ExcelWrite("B",$ItemName);
            ExcelWrite("C",$ItemPrice);
            ExcelWrite("D",$ItemExp);

            for($i = 0 ; $i < count($ItemImg); $i++){                                               
                $sheet->getCell("E".($i + 2))->setValue("https://parts.lixil.co.jp/".$ItemImg[$i]);
            } 

            //Excelファイル保存
            $writer = new Writer($spreadsheet);
            $outputPath = 'download/商品登録リスト.xlsx';
            $writer->save( $outputPath );

            echo '<div class="result"><p>・商品登録リスト.xlsx →　<a href="download/商品登録リスト.xlsx" download>ダウンロード</a></p></div>';

        }
    ?>

</body>
</html>




