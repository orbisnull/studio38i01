<?php
/**
 * User: Vasiliy Shvakin (orbisnull) zen4dev@gmail.com
 */

namespace Processor;

use Sunra\PhpSimple\HtmlDomParser;
use simple_html_dom;

class Parser
{
    /**
     * @param $htmlStr
     * @return \simple_html_dom
     */
    public function getDom($htmlStr)
    {
        return HtmlDomParser::str_get_html($htmlStr);
    }

    public function parseSolutionsLinks($htmlStr)
    {
        $dom = $this->getDom($htmlStr);
        if (!$dom instanceof \simple_html_dom) {
            throw new \Exception("Error in get dom from string: " . htmlspecialchars($htmlStr));
        }

        $tables = $dom->find("table.content");
        $table = count($tables) > 1 ? $tables[1] : $tables[0];

        $tableRows = $table->find("tr");
        array_shift($tableRows);

        $links = [];
        foreach ($tableRows as $row) {
            $link = $row->children(1)->find("a", 0)->href;
            $link = explode("=", $link);
            $links[] = $link[1];
        }
        return $links;
    }

    public function getTypeSolution($text)
    {
        if (preg_match('~Внедренное типовое решение:<\/b><\/td> <td>(.+?)<\/td>~', $text, $matches)) {
            return trim($matches[1]);
        }
        return false;
    }

    public function calcIndustry(array $inData)
    {
        $tplArray = [
            [
                "tpl" => ["Здравоохранение"],
                "item"=> "Здравоохранение"
            ],
            [
                "tpl" => ["Образование, культура, наука"],
                "item"=>"Образование и культура"
            ],
            [
                "tpl" => ["Общественное и плановое питание, гостиничный бизнес, туризм"],
                "item"=>"Хорека и туризм"
            ],
            [
                "tpl" => ["Производство, ТЭК"],
                "item"=>"Производство"
            ],
            [
                "tpl" => ["Строительство, девелопмент, ЖКХ"],
                "item"=>"Строительство и ЖКХ"
            ],
            [
                "tpl" => ["Торговля, склад, логистика, транспорт", "Торговля"],
                "item"=>"Торговля"
            ],
            [
                "tpl" => ["Торговля, склад, логистика, транспорт", "Транспорт"],
                "item"=>"Логистика и транспорт"
            ],
            [
                "tpl" => ["Торговля, склад, логистика, транспорт", "Логистика и управление складским хозяйством"],
                "item"=>"Логистика и транспорт"
            ],
            [
                "tpl" => ["Торговля, склад, логистика, транспорт", "Другие предприятия торговли, складского хозяйства и транспорта"],
                "item"=>"Логистика и транспорт"
            ],
            [
                "tpl" => ["Финансовый сектор"],
                "item"=>"Услуги"
            ],
            [
                "tpl" => ["Другие предприятия и организации"],
                "item"=>"Услуги"
            ],
        ];
        foreach($tplArray as $row) {
            if ($inData[0] === $row["tpl"][0]) {
                if (count($row["tpl"]) === 1 ) {
                    return($row["item"]);
                } else {
                    if (count($inData) > 1) {
                        if ($inData[1] === $row["tpl"][1]) {
                            return ($row["item"]);
                        }
                    }
                }
            }
        }
        return null;
    }

    public function getIndustry($text)
    {
        if (preg_match('~Отрасли:<\/b><\/td> <td>(.+?)<\/td>~', $text, $matches)) {
            $matches = explode("&gt;&gt;", $matches[1]);
            foreach ($matches as $key => $value) {
                $matches[$key] = trim($value);
            }
            return $this->calcIndustry($matches);
        }
        return false;
    }


    public function getOrganization($text)
    {
        $data = [
            "organization" => null,
            "city"         => null,
            "date"         => null,
        ];
        if (preg_match('~<h3>Внедрение</h3> <p>(.+?)<\/p>~', $text, $matches)) {
            $data["organization"] = trim(strip_tags($matches[1]));
        }
        if (preg_match('~<h3>Внедрение<\/h3>(.+)<p>(.+),+\s+(.+?[0-9]{4})+\s?<\/p>~', $text, $matches)) {
            $city = trim(strip_tags($matches[2]));
            $city = explode(" ", $city);
            $city = end($city);
            $data["city"] = $city;
            $data["date"] = trim(strip_tags($matches[3]));
        }
        return $data;
    }

    public function getArmCount($text)
    {
        if (preg_match('~<p>Общее число автоматизированных рабочих мест: <b>([0-9]+)<\/b>~', $text, $matches)) {
            return trim($matches[1]);
        }
        return false;
    }

    public function getChildUlLi($dom)
    {
        if (!$dom instanceof \simple_html_dom_node) {
            return null;
        }
        $ul = $dom->find("ul", 0);
        if (!$ul) {
            return null;
        }
        $firstLi = $ul->find("li", 0);
        if (!$firstLi) {
            return null;
        }
        return [
            "ul"   => $ul,
            "li"   => $firstLi,
            "text" => $firstLi->plaintext,
        ];
    }


    public function getFunctions($dom)
    {
        $firstNode = $this->getChildUlLi($dom);
        $firstText = $firstNode ? $firstNode["text"] : null;
        switch ($firstText) {
            case "Финансы, управленческий учет, мониторинг показателей":
                $secondNode = $this->getChildUlLi($firstNode["ul"]);
                $secondText = $secondNode ? $secondNode["text"] : null;
                switch ($secondText) {
                    case "Учет бухгалтерский, налоговый, бюджетный, включая регламентированную отчетность":
                        $thirdNode = $this->getChildUlLi($secondNode["ul"]);
                        $thirdText = $thirdNode ? $thirdNode["text"] : null;
                        switch ($thirdText) {
                            case "Бухгалтерский учет":
                            case "Налоговый учет":
                                return "Бухгалтерский, налоговый учет";
                                break;
                            case "Бюджетный учет (для бюджетных учреждений)":
                                return "Бюджетный учет";
                                break;
                            default :
                                return null;
                        }
                        break;
                    case "Бюджетирование, финансовое планирование":
                    case "Управленческий учет и расчет себестоимости методом ABC":
                    case "Управленческий учет":
                        return "Управленческий учет и бюджетирование";
                        break;
                }
                break;
            case "Учет по международным и национальным стандартам":
                return "МСФО";
                break;
            case "Управление отношениями с клиентами (CRM)":
                return "Управление продажами и отношениями с клиентами (CRM)";
                break;
            case "Управление персоналом и кадровый учет (HRM)":
                return "Кадровый учет и зарплата (HRM)";
                break;
            case "Управление продажами, логистикой и транспортом (SFM, WMS, TMS)":
                $secondNode = $this->getChildUlLi($firstNode["ul"]);
                $secondText = $secondNode ? $secondNode["text"] : null;
                switch ($secondText) {
                    case "Склад и логистика":
                    case "Транспорт":
                        return "Управление логистикой и транспортом";
                        break;
                    case "Продажи (сбыт), сервис, маркетинг":
                        return "Управление продажами и отношениями с клиентами (CRM)";
                        break;
                }
                return null;
                break;
            case "Закупки (снабжение) и управление отношениями с поставщиками":
                return "Управление продажами и отношениями с клиентами (CRM)";
                break;
            case "Документооборот (ECM)":
                return "Документооборот";
                break;
            case "Управление бизнес-процессами и ИТ-процессами ":
                return "Оптимизация бизнес-процессов";
                break;
            case "Различная отраслевая специфика":
                $secondNode = $this->getChildUlLi($firstNode["ul"]);
                $secondText = $secondNode ? $secondNode["text"] : null;
                switch ($secondText) {
                    case "Строительство" :
                        return "Отраслевой учет";
                    default:
                        return null;
                        break;
                }
                break;
            case "Другое":
                $secondNode = $this->getChildUlLi($firstNode["ul"]);
                $secondText = $secondNode ? $secondNode["text"] : null;
                switch ($secondText) {
                    case "Другое" :
                        return "Отраслевой учет";
                    default:
                        return null;
                        break;
                }
                break;
            case "Различная отраслевая специфика":
                $secondNode = $this->getChildUlLi($firstNode["ul"]);
                $secondText = $secondNode ? $secondNode["text"] : null;
                switch ($secondText) {
                    case "Производство, услуги" :
                        return "Управление производством (ERP)";
                    default:
                        return null;
                        break;
                }
                break;
            default :
                return null;

        }
    }

    public function parseSolution($htmlStr)
    {
        $dom = $this->getDom($htmlStr);
        if (!$dom instanceof \simple_html_dom) {
            throw new \Exception("Error in get dom from string: " . htmlspecialchars($htmlStr));
        }
        $content = $dom->find("table#mainBodyTable td[height=100%]", 0);
        $table = $content->find("table[height=100%]", 0);
        $mainDom = $table->find("td", 4);
        $mainData = preg_replace('/^\s+|\n|\r|\s+| +$/m', ' ', $mainDom);
//        echo $mainData;

        $data["typeSolution"] = $this->getTypeSolution($mainData);
        $data["industry"] = $this->getIndustry($mainData);
        $dataOrg = $this->getOrganization($mainData);
        $data["organization"] = $dataOrg["organization"];
        $data["city"] = $dataOrg["city"];
        $data["date"] = $dataOrg["date"];
        $data["armCount"] = $this->getArmCount($mainData);
        $data["functions"] = $this->getFunctions($mainDom);
        return $data;
    }

} 