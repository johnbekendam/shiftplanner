<?php $__env->startSection('content'); ?>
<?php echo $bodyHtml; ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layout', ['colors' => $colors], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/jb/Development/Prodrive/shiftplanner/resources/views/emails/message.blade.php ENDPATH**/ ?>