param([Parameter(Mandatory)][PSCredential]$Credential, [Parameter(Mandatory)][string]$BaseUrl)
$ErrorActionPreference = 'Stop'
$quoteHeaders = @{'Espo-Authorization'=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($Credential.UserName+':'+$Credential.GetNetworkCredential().Password))}
$quoteCreated = [Collections.Generic.List[object]]::new()
$quotePassed = [Collections.Generic.List[string]]::new()
$quoteErrors = [Collections.Generic.List[string]]::new()
function Api($Method,$Path,$Data=$null) {
    $script:quoteLastRequest="$Method $Path"
    $params=@{Uri="$BaseUrl/api/v1/$Path";Method=$Method;Headers=$quoteHeaders;TimeoutSec=45}
    if ($null -ne $Data) {$params.ContentType='application/json';$params.Body=[Text.Encoding]::UTF8.GetBytes(($Data|ConvertTo-Json -Depth 8 -Compress))}
    Invoke-RestMethod @params
}
function NewRecord($Type,$Data) {
    $r=Api POST $Type $Data
    $quoteCreated.Add(@{type=$Type;id=$r.id})
    return $r
}
function Verify($Label,$Condition) {
    if (!$Condition) {throw "验证失败：$Label"}
    $quotePassed.Add($Label)
}
function GetOriginalState {
    $records=(Api GET 'Quote?maxSize=200').list | Where-Object {$_.description -notlike 'CODEX-INTEGRATION-TEST*'}
    $data=$records|Sort-Object id|ConvertTo-Json -Depth 8 -Compress
    [Convert]::ToHexString([Security.Cryptography.SHA256]::HashData([Text.Encoding]::UTF8.GetBytes($data)))
}
$quoteBefore=GetOriginalState
$quoteOriginalCount=(Api GET 'Quote?maxSize=1').total
try {
    $project=NewRecord Opportunity @{name='Codex 报价验证（测试）';amount=0;amountCurrency='CNY';closeDate=(Get-Date -Format 'yyyy-MM-dd');description='CODEX-INTEGRATION-TEST：非业务项目'}
    $quote=NewRecord Quote @{opportunityId=$project.id;taxRate=13;discountAmount=30;totalCurrency='CNY';description='CODEX-INTEGRATION-TEST：非业务报价'}
    $products=Api GET 'CProduct?maxSize=1'
    if (!$products.list.Count) {throw '没有可用于关联测试的产品'}
    $product=$products.list[0]
    $a=NewRecord QuoteItem @{quoteId=$quote.id;catalogProductId=$product.id;productName='测试占位';quantity=2;unitPrice=100;discountPercent=10}
    $b=NewRecord QuoteItem @{quoteId=$quote.id;productName='Codex 测试服务';quantity=3;unitPrice=50;discountPercent=0}
    Verify '产品名称规格带入' ($a.productName -eq $product.name -and $a.specification -eq $product.spec)
    Verify '两行金额计算' ($a.amount -eq 180 -and $b.amount -eq 150)
    $q=Api GET "Quote/$($quote.id)"
    Verify '小计折扣税额总计' ($q.subtotal -eq 330 -and $q.taxAmount -eq 39 -and $q.total -eq 339)
    Verify '人民币币种一致' ($q.totalCurrency -eq 'CNY' -and $a.amountCurrency -eq 'CNY')
    $linked=Api GET "Opportunity/$($project.id)/quotes?maxSize=20"
    Verify '项目关联报价' ($linked.list.id -contains $quote.id)
    $null=Api PUT "QuoteItem/$($a.id)" @{quantity=1}
    $q=Api GET "Quote/$($quote.id)"
    Verify '修改明细重算表头' ($q.subtotal -eq 240 -and $q.total -eq 237.3)
    $null=Api PUT "QuoteItem/$($a.id)" @{quantity=2;productName='Codex 产品快照';specification='测试规格快照'}
    $null=Api PUT "Quote/$($quote.id)" @{status='Sent'}
    $copied=Api POST "Quote/$($quote.id)/duplicateAsNewVersion" @{}
    $quoteCreated.Add(@{type='Quote';id=$copied.id})
    $copy=Api GET "Quote/$($copied.id)"
    $lines=Api GET "Quote/$($copied.id)/quoteItems?maxSize=20"
    foreach($line in $lines.list) {$quoteCreated.Add(@{type='QuoteItem';id=$line.id})}
    Verify '复制生成草稿新版本' ($copy.version -eq 2 -and $copy.parentQuoteId -eq $quote.id -and $copy.status -eq 'Draft')
    Verify '复制完整明细及总额' ($lines.list.Count -eq 2 -and $copy.total -eq 339 -and $copy.opportunityId -eq $project.id)
    $copiedProduct=$lines.list|Where-Object {$_.catalogProductId -eq $product.id}
    Verify '复制保留产品快照不重新取主数据' ($copiedProduct.productName -eq 'Codex 产品快照' -and $copiedProduct.specification -eq '测试规格快照')
    $null=Api DELETE "QuoteItem/$($b.id)"
    $q=Api GET "Quote/$($quote.id)"
    Verify '删除明细重算表头' ($q.total -eq 169.5)
    $other=NewRecord Quote @{taxRate=0;discountAmount=0;totalCurrency='CNY';description='CODEX-INTEGRATION-TEST：移动明细测试'}
    $null=Api PUT "QuoteItem/$($a.id)" @{quoteId=$other.id}
    $q=Api GET "Quote/$($quote.id)"
    $o=Api GET "Quote/$($other.id)"
    Verify '移动明细重算新旧报价' ($q.total -eq 0 -and $o.total -eq 180)
    $rejected=$false
    try {$null=Api POST QuoteItem @{quoteId=$other.id;productName='非法测试';quantity=-1;unitPrice=1}} catch {$rejected=([int]$_.Exception.Response.StatusCode -eq 400)}
    Verify '拒绝负数量' $rejected
    Verify '历史报价未被修改' ((GetOriginalState) -eq $quoteBefore)
} catch {
    $quoteErrors.Add($script:quoteLastRequest+': '+$_.Exception.Message+' '+$_.ErrorDetails.Message)
} finally {
    # 只使用普通 DELETE 软删除本次新建的测试记录，不执行永久删除或修改历史记录。
    foreach($type in @('QuoteItem','Quote','Opportunity')) {
        foreach($entry in @($quoteCreated | Where-Object {$_.type -eq $type})) {
            try {$null=Api DELETE "$type/$($entry.id)"} catch {
                if ([int]$_.Exception.Response.StatusCode -ne 404) {$quoteErrors.Add("测试清理失败：$type/$($entry.id)")}
            }
        }
    }
}
[PSCustomObject]@{passed=$quotePassed.Count;checks=@($quotePassed);errors=@($quoteErrors);originalQuoteCount=$quoteOriginalCount;finalQuoteCount=(Api GET 'Quote?maxSize=1').total;originalUnchanged=((GetOriginalState) -eq $quoteBefore)} | ConvertTo-Json -Depth 5
if ($quoteErrors.Count) {exit 1}
