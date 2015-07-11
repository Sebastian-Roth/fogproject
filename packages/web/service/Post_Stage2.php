<?php
require_once('../commons/base.inc.php');
try {
    $Host = $FOGCore->getHostItem(false);
    // Task for Host
    $Task = $Host->get('task');
    if (!$Task->isValid()) throw new Exception(sprintf('%s: %s (%s)', _('No Active Task found for Host'), $Host->get('name'), $MACAddress));
    $TaskType = new TaskType($Task->get('typeID'));
    // Get the storage group
    $StorageGroup = $Task->getStorageGroup();
    if ($TaskType->isUpload() && !$StorageGroup->isValid())
        throw new Exception(_('Invalid Storage Group'));
    // Get the storage node.
    $StorageNode = $StorageGroup->getMasterStorageNode();
    if ($TaskType->isUpload() && !$StorageNode)
        throw new Exception(_('Could not find a Storage Node. Is there one enabled within this Storage Group?'));
    // Image Name store for logging the image task later.
    $Image = $Task->getImage();
    $ImageName = $Image->get('name');
    // Sets the class for ftp of files and deletion as necessary.
    $ftp = $FOGFTP;
    // Sets the mac address for tftp delete later.
    $mactftp = strtolower(str_replace(':','-',$_REQUEST['mac']));
    // Sets the mac address for ftp upload later.
    $macftp = strtolower(str_replace(':','',$_REQUEST['mac']));
    // Set the src based on the image and node path.
    $src = $StorageNode->get('ftppath').'/dev/'.$macftp;
    // Where is it going?
    $dest = $StorageNode->get('ftppath').'/'.$_REQUEST['to'];
    //Attempt transfer of image file to Storage Node
    $ftp->set(host,$StorageNode->get(ip))
        ->set(username,$StorageNode->get(user))
        ->set(password,$StorageNode->get(pass));
    if (!$ftp->connect())
        throw new Exception(_('Storage Node: '.$StorageNode->get(ip).' FTP Connection has failed!'));
    // Try to delete the file.  Doesn't hurt anything if it doesn't delete anything.
    $ftp->delete($dest);
    if ($ftp->rename($dest,$src)||$ftp->put($dest,$src))
        ($_REQUEST['osid'] == '1' || $_REQUEST['osid'] == '2' ? $ftp->delete($StorageNode->get('ftppath').'/dev/'.$macftp) : null);
    else
        throw new Exception(_('Move/rename failed.'));
    $ftp->close();
    // If image is currently legacy, set as not legacy.
    if ($Image->get('format') == 1)
        $Image->set('format',0)->save();
    // Complete the Task.
    $Task->set('stateID','4')->set('pct','100')->set('percent','100');
    if (!$Task->save())
        throw new Exception(_('Failed to update Task'));
    $EventManager->notify('HOST_IMAGEUP_COMPLETE', array(HostName=>$Host->get('name')));
    // Log it
    $ImagingLogs = $FOGCore->getClass('ImagingLogManager')->find(array('hostID' => $Host->get('id')),'','','','','','','id');
    $id = max((array)$ids);
    // Last Uploaded date of image.
    $Image->set('deployed',$FOGCore->formatTime('now','Y-m-d H:i:s'))->save();
    // Log
    $il = new ImagingLog($id);
    $il->set('finish',$FOGCore->formatTime('now','Y-m-d H:i:s'))->save();
    // Task Logging.
    $TaskLog = new TaskLog($Task);
    $TaskLog->set('taskID',$Task->get('id'))
        ->set('taskStateID',$Task->get('stateID'))
        ->set('createdTime',$Task->get('createdTime'))
        ->set('createdBy',$Task->get('createdBy'))
        ->save();
    print '##';
}
catch (Exception $e)
{
    print $e->getMessage();
}
