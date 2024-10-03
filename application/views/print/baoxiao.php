
<?php
$free_audit = $one->is_free_audit==1?"<span style='border:1px solid #000000;padding:2px 3px;'>管理费用免审核</span>":"";?>
<table border="0" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td valign="bottom" height="30" colspan="2">
            <div style='text-align:center;font-weight:bold;font-size:20px;'>费用报销审批单</div>
            <div style='margin-top:-22px;font-size:16px;font-weight:bold;text-align:right'>
                <?= $one->id?>
                <?php if ($one->print_num > 1) { ?>
                    &nbsp;&nbsp;②
                <?php } ?>
            </div>
        </td>
    </tr>
    <tr>
        <td  height="35">报销部门：<?= $one->department_name ?>（<?= $one->books_name ?>）</td>
        <td align="right"><?= date('Y年m月d日', $one->create_time) ?></td>
    </tr>

    <tr>
        <td colspan="2">
            <table border="1" bordercolor="#000" cellSpacing="0" cellPadding="2" width="100%" style='font-size:14px;'>
                <tbody>
                    <tr>
                        <td width="10%">费用科目</td>
                        <td height="35" width="30%" align="center">用&nbsp;&nbsp;&nbsp;途</td>
                        <td width="18%" align="center">金额(元)</td>
                        <td rowspan=4 width="20" style="width:20px !important;">备<br/>注</td>
                        <td rowspan=4 align="center" width="30%"><?= $one->remark ?>&nbsp;

                            <br>
                            付款形式：<?if($one->is_gongzika==1){echo "工资卡";} else if($one->is_gongzika==2){echo "内转";}else{echo "其它";}?>
                        </td>
                    </tr>
                    <?php
                    $check=$this->db->where(array('tablename'=>$this->tablename,'parent'=>$one->id,'type'=>"check"))->get('base_approval')->row();
                    $check_user=$check?cache_user($check->forward_uid):array();
                    ?>
                    <?php 
                    $details=$one->details;
                    for ($i = 0; $i < 6; $i++) { ?>
                        <?php
                        if (isset($details[$i])) {?>
                            <?php if ($i == 3) { ?>
                                <tr>
                                    <td><?=$details[$i]->subject_name?></td>
                                    <td height="35" >&nbsp;<?=$details[$i]->title ?></td>
                                    <td >&nbsp;<?=$details[$i]->amount ?></td>
                                    <td  rowspan=4>领<br/>导<br/>审<br/>批</td>
                                    <td  rowspan=4><?= $check ? $this->user_model->get_name($check->uid) : $this->user_model->get_name($one["uid"]) ?><br><br>&nbsp;<span style='font-size:24px;font-weight:bold;'><?= $free_audit ?></span></td>
                                </tr>
                            <?php } else { ?>
                                <tr>
                                    <td><?=$details[$i]->subject_name?></td>
                                    <td height="35" >&nbsp;<?=$details[$i]->title ?></td>
                                    <td >&nbsp;<?=$details[$i]->amount ?></td>
                                </tr>
                                <?php
                            }
                        } else {
                            ?>
                            <?php if ($i == 3) { ?>
                                <tr>
                                    <td height="35" >&nbsp;</td>
                                    <td height="35" >&nbsp;</td>
                                    <td >&nbsp;</td>
                                    <td  rowspan=4 >领<br/>导<br/>审<br/>批</td>
                                    <td  rowspan=4><span style="font-size:18px">
                                            <?=$check_user?$check_user->realname:""?></span><br><br>&nbsp;<span style='font-size:24px;font-weight:bold;'><?= $free_audit ?></span>
                                    </td>
                                </tr>
                            <?php } else { ?>
                                <tr>
                                    <td height="35" >&nbsp;</td>
                                    <td height="35" >&nbsp;</td>
                                    <td >&nbsp;</td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    <?php } ?>
                    <tr>
                        <td  height="35" colspan="2">合&nbsp;&nbsp;&nbsp;计</td>
                        <td ><?= $one->total?></td>
                    </tr>
                    <tr>
                        <td align=left  colspan="2" height="35" >&nbsp;金额（大写）&nbsp;<?= money_chinese(floatval($one->total)) ?></td>
                        <td align=left>&nbsp;原借款&nbsp;&nbsp;&nbsp;&nbsp;元&nbsp;</td>
                        <td align=left colspan="2" align="right" style="text-align:right;" >&nbsp;应退余款&nbsp;&nbsp;&nbsp;&nbsp;元&nbsp;</td>
                    </tr>
            </table> 
        </td>
    </tr>
    <tr>
        <td colspan=2>
            <table width=100% cellpadding=0 cellspacing=0 border=0 style="margin-top: 15px;">
                <tr>
                    <td width=30% style='height:1cm;' align=left>会计主管：</td>
                    <td width=15% align=left>复核：</td>
                    <td width=15% align=left>出纳：</td>
                    <td width=20% align=left>报销人：<?= $one->insert_user->realname ?></td>
                    <td width=20% align=left>领款人：</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
