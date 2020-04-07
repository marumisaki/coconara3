<?php

//共通変数・関数ファイルを読込み
require('function.php');

debug('==========');
debug('ユーザー情報投稿・編集ページ');
debug('==========');
debugLogStart();

//ログイン認証
require('userAuth.php');

//================================
// 画面処理
//================================
// DBからユーザーデータを取得
$dbh = dbConnect();
$dbFormData = getUser($dbh,$_SESSION['u_id']);

//print_r($dbHistoryData);

debug('userEdit:取得したユーザー情報：'.print_r($dbFormData,true));

//該当ユーザー以外がURLを入力した場合
if(isset($_SESSION['u_id'])){
  if($_SESSION['u_id'] === $dbFormData['id']){
    $u_id = $_SESSION['u_id'];
  }else{
    header("location: userMypage.php");
    exit;
  }
}else if(isset($_SESSION['c_id'])){
  header("location: companyMypage.php");
   exit;
}else{
  header("location: top.php");
  exit;
}

// post送信されていた場合
if(!empty($_POST)){
  debug('userEdit:POST送信があります。');
  debug('userEdit:POST情報：'.print_r($_POST,true));
  debug('userEdit:FILE情報：'.print_r($_FILES,true));

  //変数にユーザー情報を代入
  $u_name = $_POST['u_name'];
  $email = $_POST['email'];
	$description = $_POST['description'];
	/*
	$history_name0 = $_POST['history_name0'];
	$history_name1 = $_POST['history_name1'];
	$history_name2 = $_POST['history_name2'];
	$start_date0 = $_POST['start_date0'];
	$start_date1 = $_POST['start_date1'];
	$start_date2 = $_POST['start_date2'];
	$end_date0 = $_POST['end_date0'];
	$end_date1 = $_POST['end_date1'];
	$end_date2 = $_POST['end_date2'];
	$detail0 = $_POST['detail0'];
	$detail1 = $_POST['detail1'];
	$detail2 = $_POST['detail2'];
	*/
	$goal = $_POST['goal'];
  //画像をアップロードし、パスを格納
  $pic = ( !empty($_FILES['pic']['name']) ) ? uploadImg($_FILES['pic'],'pic') : '';
  // 画像をPOSTしてない（登録していない）が既にDBに登録されている場合、DBのパスを入れる（POSTには反映されないので）
  $pic = ( empty($pic) && !empty($dbFormData['pic']) ) ? $dbFormData['pic'] : $pic;

  //DBの情報と入力情報が異なる場合にバリデーションを行う
  if($dbFormData['u_name'] !== $u_name){
		//未入力チェック
    validRequired($u_name, 'u_name');
    //名前の最大文字数チェック
    validMaxLen($u_name, 'u_name');
  }
  if($dbFormData['email'] !== $email){
    //emailの最大文字数チェック
    validMaxLen($email, 'email');
    if(empty($err_msg['email'])){
      //emailの重複チェック
      validEmailDupUser($dbh,$email);
    }
    //emailの形式チェック
    validEmail($email, 'email');
    //emailの未入力チェック
    validRequired($email, 'email');
  }
  if(empty($err_msg)){
    debug('バリデーションOKです。');

    //例外処理
    try {
      // DBへ接続
      $dbh = dbConnect();
      // SQL文作成
      $sql = 'UPDATE u_profile SET u_name = :u_name, email = :email, description = :description, goal = :goal, pic = :pic WHERE id = :u_id';
      $data = array(':u_name' => $u_name , ':email' => $email, 'description' => $description, ':goal' => $goal, ':pic' => $pic, ':u_id' => $dbFormData['id']);
      // クエリ実行
			$stmt = queryPost($dbh, $sql, $data);

			$sql = "DELETE FROM history WHERE u_id = :u_id";
			$data = array(':u_id' => $dbFormData['id']);
			$stmt = queryPost($dbh, $sql, $data);

			foreach( $_POST['history_name'] as $key => $history_name )
			{
				$sql = 'INSERT INTO history (history_name,start_date,end_date,detail,u_id,create_date) VALUES(:history_name,:start_date,:end_date,:detail,:u_id,:create_date)';
				$data = Array();
				$data = array(':history_name' => $history_name , ':start_date' => $_POST['start_date'][$key], 'end_date' => $_POST['end_date'][$key], ':detail' => $_POST['detail'][$key],':u_id' => $_SESSION['u_id'],':create_date' => date('Y-m-d H:i:s'));
				$stmt = queryPost($dbh, $sql, $data);
			}

			if($stmt){
				$_SESSION['msg_success'] = SUC02;
				debug('マイページへ遷移します。');
				//header("Location:userMypage.php"); //マイページへ
			}
		} catch (Exception $e) {
			error_log('エラー発生:' . $e->getMessage());
			$err_msg['common'] = MSG07;
		}
	}
}

$dbHistoryData = getHistory($dbh,$_SESSION['u_id']);
debug('userEdit:取得した職歴：'.print_r($dbHistoryData,true));

debug('画面表示処理終了 <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<');
?>
<?php
	$siteTitle = 'ユーザー情報編集';
	require('head.php');
?>

<body>

<!-- メニュー -->
<?php
	require('header.php');
?>
<script>

	// 職歴欄の最大表示件数を定義
	var view_history_limit = 3;

	// 職歴欄の最少表示件数を定義
	var view_history_min = 1;

	// 現在表示されている職歴欄の件数を定義
	var history_box_count = 1;

      $(function(){

    	// 初期表示時、職歴欄が複数存在している場合は現在表示している職歴欄数を変数に代入
      	if( $("[name=history_area]").length >= 1 ){
      		history_box_count = $("[name=history_area]").length;
      	}else{
      		$("[name=delete-history]:first").addClass('js-hidden');
      	}

    	// 画面も初期表示時、職歴欄が表示件数の上限に達している場合、
    	// 「職歴を追加」ボタンを非表示にする
		if( history_box_count >= view_history_limit ){
			$('#add_history_button').addClass('js-hidden');
		}

    	// 「職歴を追加」ボタンが押下された場合
    	$("#add_history_button").click(function(){

	      	// 職歴欄が1つしか存在していない場合、ダストボックス画像を表示するしておく（表示した状態でコピーするため）
	      	if( $("[name=history_area]").length <= 1 ){
	      		$("[name=delete-history]:first").removeClass('js-hidden');
	      	}

        	// 最初の職歴欄をコピーする
    		$("[name=history_area]:first").clone().appendTo('#history_box');
    		history_box_count++;

    		// 職歴欄が3つ以上の場合、「職歴を追加」ボタンを非表示にする
    		if( history_box_count >= view_history_limit || history_box_count <= view_history_min ){
    			$('#add_history_button').addClass('js-hidden');
    		}

    		// コピーした最後の職歴欄の「職業経歴:**」のテキストを変更する
    		$("[name=history_area]:last [name=history_no]").html(history_box_count);

    		// コピーした最後の職歴欄の各入力項目を未入力状態に設定する
    		$("[name=history_area]:last [name='history_name[]']").val("");
    		$("[name=history_area]:last [name='start_date[]']").val("");
    		$("[name=history_area]:last [name='end_date[]']").val("");
    		$("[name=history_area]:last [name='detail[]']").val("");
    	});
      });

		// ダストボックス画像が押下された場合
		function delete_history(object){
			// 該当の職歴欄であるdiv要素(name="history_area")を削除する
			// js-hiddenを使用すると非表示になるだけで、要素自体は存在しているため「登録する」ボタンを押下すると
			// js-hiddenで非表示となっている要素までリクエストで送信されてしまうためjs-hiddenではなくremoveを使用しています。
			object.parent().parent().remove();
			history_box_count = history_box_count - 1;

			// 職歴欄が最大表示件数未満となった場合、「職歴を追加」ボタンを表示する
			if( history_box_count < view_history_limit ){
				$('#add_history_button').removeClass('js-hidden');
			}

			// 各職歴欄の「職業経歴:**」を再度採番する
			var count = 1;
			$.each($("[name=history_area]") ,function(){
				$(this).find("[name=history_no]").html(count);
				count++;
			});

			// 職歴欄が複数存在している場合は職歴欄のダストボックス画像を表示する
	      	if( $("[name=history_area]").length > 1 ){
	      		$("[name=delete-history]:first").removeClass('js-hidden');

	      	// 職歴欄が1つしか存在していない場合、ダストボックス画像を非表示にする
	      	}else{
	      		$("[name=delete-history]:first").addClass('js-hidden');
	      	}
		}
</script>
<!-- メインコンテンツ -->
<div class="container">
	<div class="panel--oblong u-mt_3l">
		<h1 class="panel--oblong__title u-center">プロフィール登録</h1>
	</div>
	<div>
		<form action="" method="post" class="form" enctype="multipart/form-data">
			<div class="u-flex-reverse">
				<div class="u-width-70 panel--white u-left u-pb_xxl u-pt_xxl u-pl_xxl u-pr_xxl u-radius__m u-mb_xl u-mt_4l">
					<div class="area-msg">
						<?php
						if(!empty($err_msg['common'])) echo $err_msg['common'];
						?>
					</div>
					<label class="<?php if(!empty($err_msg['u_name'])) echo 'err'; ?>">
						名前<span class="badge-required">＊必須</span>
						<input class="input" type="text" placeholder="フルネーム" name="u_name" value="<?php echo getFormdata($dbFormData, 'u_name');?>">
					</label>
					<div class="area-msg">
						<?php
						if(!empty($err_msg['u_name'])) echo $err_msg['u_name'];
						?>
					</div>
					<label class="<?php if(!empty($err_msg['email'])) echo 'err'; ?>">
						Email<span class="badge-required">＊必須</span>
						<input class="input" type="text" placeholder="メールアドレス　（例　web@mail.com）" name="email" value="<?php echo getFormdata($dbFormData, 'email');?>">
					</label>
					<div class="area-msg">
						<?php
						if(!empty($err_msg['email'])) echo $err_msg['email'];
						?>
					</div>
					<label class="<?php if(!empty($err_msg['description'])) echo 'err'; ?>">
						自己紹介
						<textarea class="textarea" cols="30" rows="10" placeholder="年齢、居住地、エンジニアになりたい理由、プログラミングスキル、長所や短所などご自由にご記入ください。
(例　東京都在住の25歳です。現在は食品メーカーの営業をしています。元々プログラミングに興味があり〇〇というスクールに半年間通って卒業しました。初心者レベルのフロントエンドのスキルはあります。最新PHPの勉強もしています。)" name="description"><?php echo getFormdata($dbFormData, 'description');?></textarea>
					</label>
					<div class="area-msg">
						<?php
						if(!empty($err_msg['description'])) echo $err_msg['description'];
						?>
					</div>
					<div class="u-width-100" id="history_box">

						職歴（最大3つまで）
						<?php for($a = 0; $a <= 2; $a++){?>
						<?php  foreach( $dbHistoryData as $key => $db_data ){ ?>
							<div name="history_area" class="u-flex-default">
								<div class="js-history u-width-100" name="history">
									<label class="<?php if(!empty($err_msg[''])) echo 'err'; ?>">
										企業名
										<input class="input" type="text" placeholder="株式会社〇〇〇〇" name="history_name[]" value="<?php if(!empty($db_data)) echo getFormdataArray($db_data, 'history_name', $key);?>">
									</label>
									<label class="<?php if(!empty($err_msg[''])) echo 'err'; ?>">
										入社日・退社日
										<div class="u-flex-between">
											<input class="input u-width-40" type="date" name="start_date[]" value="<?php if(!empty($db_data))echo getFormdataArray($db_data, 'start_date', $key);?>"><span class="u-mt_m">〜</span>
											<input class="input u-width-40 u-mr_3l" type="date" name="end_date[]" value="<?php if(!empty($db_data))echo getFormdataArray($db_data, 'end_date', $key);?>">
										</div>
									</label>
									<label class="<?php if(!empty($err_msg['description'])) echo 'err'; ?>">
										業務内容など
										<textarea class="textarea" cols="30" rows="10" placeholder="業務内容や役職などをご記入ください。　（例　前職ではメーカーで法人営業をしていました。10名チームのリーダーとして、目標の管理などのマネジメントを行なっていました。）" name="detail[]"><?php if(!empty($db_data)) echo getFormdataArray($db_data, 'detail', $key);?></textarea>
									</label>
									<button type="button" name="delete-history" class="delete-history<?php echo $key; ?>" onclick="delete_history($(this));"><i class="fas fa-trash-alt"></i></button>
								</div>
							</div>
						<?php }} ?>
					</div>
					<input class="add-history u-width-100 u-center u-m_auto" id="add_history_button" type="button" value="職歴を追加"/>
					<label class="<?php if(!empty($err_msg['goal'])) echo 'err'; ?>">
						今後の目標
						<textarea class="textarea" cols="30" rows="10" placeholder="将来のありたい姿などご記入ください。（例　○年○月末に現在の職場を退職する予定なので、それまでにスキルアップしてフロントエンドエンジニアとして転職したいです。）" name="goal"><?php echo getFormdata($dbFormData, 'goal');?></textarea>
					</label>
					<div class="area-msg">
						<?php
						if(!empty($err_msg['goal'])) echo $err_msg['goal'];
						?>
					</div>
				</div>
				<label class="js-area-drop area-drop">
					プロフィール写真を<span class="td-ul">アップロードする</span><span class="err"></span>
					<input type="hidden" name="MAX_FILE_SIZE" value="3145728">
					<input class="js-form js-input-file input-file" type="file" name="pic">
					<img src="<?php echo getFormdata($dbFormData,'pic');?>" alt="" class="js-prev-img prev-img--default">
				</label>
				<div class="area-msg">
					<?php
					if(!empty($err_msg['pic'])) echo $err_msg['pic'];
					?>
				</div>
			</div>
			<input type="submit" value="登録する" class="button button--yellow u-width-30 u-block u-m_auto u-mt_xl u-mb_4l">
			</form>
	</div>
</div>
<!-- footer -->
<?php
	require('footer.php');
?>
