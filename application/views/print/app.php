<div style="width:180mm;margin:1em auto 0 auto;">
    <table border="0" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td valign="bottom" height="30" colspan="2" style="padding:1em 0;">
                <div style='text-align:center;font-size:25px;'>U12文化产业集团<?= $lego->name ?>申请单</div>
                <div style='margin-top:-22px;font-size:16px;font-weight:bold;text-align:right;'>NO.<?=str_pad($row->id, 6, '0', STR_PAD_LEFT) ?>&nbsp;&nbsp;</div>
                <div style="border-bottom:3px solid #777;margin-bottom: 5px;margin-top: 10px;"></div>
                <div style="border-bottom:1px solid #777"></div>
            </td>

        </tr>
        <tr>
            <td style='height:1cm;' align="left">申请人：<?= $row->insert_uid ?></td>
            <td align="right">申请时间：<?= $row->insert_time ?></td>
        </tr>
        <tr>
            <td colspan="2">
                <table border="1" bordercolor="#000" cellSpacing="0" cellPadding="2" width="100%" style='font-size:14px;'>
                    <tbody>
                        <?php
                        foreach ($lego_fields as $field):
                            $name = $field->name;
                            $page = json_decode($field->page, true);
                            $check = $page && isset($page["detail"]) && $page["detail"];
                            if ($check):
                                ?>
                                <tr>
                                    <td width="12%" align="center"><?= $field->name_cn ?></td>
                                    <td height="35" width="88%" style="padding-left: 5px;"> 
                                        <?= $field->form_type=="date"?date("Y-m-d",$row->$name):$row->$name ?>
                                        <?=$field->form_unit?$field->form_unit:""?>
                                    </td>
                                </tr>
                                <?php
                            endif;
                        endforeach;
                        ?>
                </table> 
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <table border="1" bordercolor="#000" cellSpacing="0" cellPadding="2" width="100%" style='font-size:14px;'>
                    <tbody>
                        <tr>
                            <td height="50" width="17%" align="center">组织人<br>签字</td>
                            <td width="16%"></td>
                            <td width="18%" align="center">部门负责人<br>签字</td>
                            <td width="16%"></td>
                            <td width="17%" align="center">人力资源<br>签字</td>
                            <td width="16%"></td>
                        </tr>
                </table> 
            </td>
        </tr>
    </table>
</div>