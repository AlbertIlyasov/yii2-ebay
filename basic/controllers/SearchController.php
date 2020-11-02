<?php

namespace app\controllers;

use app\models\CurrencyModel;
use Yii;
use app\models\User;
use app\models\Action;
use app\models\Prices;
use app\models\EbayItem;
use app\models\Settings;
use app\models\File;
use app\models\Register;
use app\models\SearchImagesForm;
use app\models\SearchLog;
use app\models\SearchImages;
use yii\web\Response;

class SearchController extends AbstractController
{
    public function actionIndex()
    {
//        $artikul = 1760;
//        $searchLog = SearchLog::add($artikul);
//        (new \app\models\PromEnergoAvtomatikaParser)
//            ->setArtikul($artikul)
//            ->run()
//        ;
        if (!User::verifyAuthorization(Action::ACTION_SEARCH)) {
            return;
        }

        if (Settings::isLogExpireEnabled() && Settings::isCurrentTimeTimeToDeleteExpiredFiles()) {
            File::removeExpiredFiles(File::getBasePath());
            Register::setLogExpireValidateDate(date('Y-m-d'));
        }

        $form = new \app\models\SearchForm;
        //если отправили данные для поиска
        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            SearchLog::add($form->artikul, $form->qty);
            (new Prices)->search($form->artikul, $form->qty);
            return Yii::$app->getResponse()->redirect(['/search']);
        }

        $id = User::hasAuthUserAdminRole() && (int) Yii::$app->request->get('id')
            ? (int) Yii::$app->request->get('id')
            : SearchLog::find()->where(['userId' => User::getAuthUserId()])->orderBy(['id' => \SORT_DESC])->one()->id;

        //если нет результатов поиска, отобразим только форму поиска
        if (!$id) {
            return $this->render('index', [
                'model' => $form,
            ]);
        }

        $searchLog = SearchLog::loadById($id);

        $form->artikul = $searchLog->query;
        $form->qty = $searchLog->qty;
        $prices = new Prices;
        $pricesData = $prices->getPrices($id);

        $pricesData['local'] = new \yii\data\ArrayDataProvider([
            'allModels' => $pricesData['local'],
            'sort' => [
                'attributes' => [
                    'artikul',
                    'price',
                    'rate',
                    'supplierId',
                    'name',
                    'unitMeasurementId',
                    'qtyMin',
                    'deliveryTime',
                ],
            ],
        ]);

        $currency = new CurrencyModel;
        return $this->render('index', [
            'model' => $form,
            'prices' => $pricesData,
            'margins'=> $prices->getMargins(),
            'searchLog' => $searchLog,
            'rurEurRate' => $currency->getExchangeRubRate(CurrencyModel::EUR, $currency->getCurDate()),
        ]);
    }

    public function actionLog()
    {
        if (!User::verifyAuthorization(Action::ACTION_SEARCH_LOG)) {
            return;
        }

        $sort = new \yii\data\Sort([
            'attributes' => [
                'id',
                'query',
                'userId',
                'created',
            ],
        ]);
        // $sort->enableMultiSort = true;
        $sort->defaultOrder = [
            'id' => SORT_DESC,
        ];

        $artikul = (string)Yii::$app->request->get('query');
        $userId  =    (int)Yii::$app->request->get('userId');
        $created = (string)Yii::$app->request->get('date');

        $where = [];
        if ($artikul) {
            $where['query'] = $artikul;
        } elseif ($userId) {
            $where['userId'] = $userId;
        } elseif ($created) {
            $where = ['between', 'created', $created, $created . ' 23:59:59'];
        }

        $query = SearchLog::find()->orderBy($sort->orders);
        if ($where) {
            $query->where($where);
        }

        $dataProvider = new \yii\data\ActiveDataProvider([
            'query' => $query,
            'sort'  => $sort,
            'pagination' => ['pageSize' => \app\models\Settings::getSettings()->gridPageSize],
        ]);

        return $this->render('log', [
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionFiles()
    {
        if (!User::verifyAuthorization(Action::ACTION_SEARCH_LOG)) {
            return;
        }

        $id = (string) Yii::$app->request->get('id');
        $log = SearchLog::findOne($id);
        if (!$log) {
            throw new \yii\web\NotFoundHttpException('Передайте верный ID поиска.');
        }

        $fileModel = new \app\models\File($log);
        $file = (string) Yii::$app->request->get('file');
        $raw  = (bool) Yii::$app->request->get('raw');
        if ('zip' == pathinfo($file, PATHINFO_EXTENSION)) {
            $raw = $fileModel->getRaw($file);
            $fileFullname = $fileModel->getPath() . $file;
            return Yii::$app->response->sendFile($fileFullname, basename($fileFullname));
        }
        if ($file && !$raw) {
            $data = $fileModel->getBody($file);
            $dataDecoded = json_decode($data);

            try {
                $xml = simplexml_load_string($data);
                if (strstr($data, 'encoding="windows-1251"')) {
                    Header('Content-Type: text/xml; charset=windows-1251');
                } else {
                    Header('Content-Type: text/xml;');
                }
                echo $data;
                exit;
            } catch (\Exception $e) {}

            if (!$dataDecoded) {
                echo $data;
                exit;
            }

            Yii::$app->response->format = Response::FORMAT_JSON;
            return $dataDecoded;
        } elseif ($file && $raw) {
            echo $fileModel->getRaw($file);
            exit;
        }

        return $this->render('files', [
            'log' => $log,
            'files' => $fileModel->getFiles(),
        ]);
    }

    public function actionImages()
    {
        if (!User::verifyAuthorization(Action::ACTION_SEARCH_IMG)) {
            return;
        }

        $jobStat = null;
        $downloadUrl = null;
        $stat = [];
        $isSearchActive = false;

        $form = new SearchImagesForm;
        if ($form->load(Yii::$app->request->post()) && $form->validate()) {
            SearchLog::add($form->artikuls);
            $isSearchActive = true;
            try {
                ($search = new SearchImages(explode("\r\n", $form->artikuls), $form->conditions))->search();
                $downloadUrl = $search->getDownloadUrl();
            } catch (\app\models\DurationLimitException $e) {
            }
            $stat = $search->getStat();
        }

        return $this->render('images', [
            'model' => $form,
            'stats' => $stat,
            'file'  => $downloadUrl,
            'isAutosubmitEnabled' => $isSearchActive && !$downloadUrl,
        ]);
    }

    public function actionTestTime()
    {
        if (!User::verifyAuthorization(Action::ACTION_SEARCH_IMG)) {
            return;
        }

        SearchLog::add('DurationTest');
        (new SearchImages([], []))->streamTestTime();
        die('ok, check history logs');
    }
}
