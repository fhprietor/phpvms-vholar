@section('scripts')

{{-- Modal: Motivo de Rechazo --}}
<div class="modal fade" id="adminRejectModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        <h4 class="modal-title"><i class="ti-close text-danger"></i> Motivo de Rechazo</h4>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Motivo <span class="text-danger">*</span></label>
          <textarea id="adminRejectReason" class="form-control" rows="4"
            placeholder="Describe el motivo del rechazo para informar al piloto..."></textarea>
          <span id="adminRejectReasonError" class="text-danger" style="display:none;">
            El motivo es requerido.
          </span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-warning" id="adminRejectConfirm">Confirmar Rechazo</button>
      </div>
    </div>
  </div>
</div>

{{-- Modal: Nota de Aceptación --}}
<div class="modal fade" id="adminAcceptModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        <h4 class="modal-title"><i class="ti-check text-success"></i> Observación del Admin</h4>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Nota <small class="text-muted">(opcional)</small></label>
          <textarea id="adminAcceptNote" class="form-control" rows="3"
            placeholder="Observación para el piloto (déjalo vacío para aceptar sin nota)..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success" id="adminAcceptConfirm">Confirmar Aceptación</button>
      </div>
    </div>
  </div>
</div>

<script>
  const changeStatus = async (values, fn) => {
    console.log('Changing PIREP ' + values.pirep_id + ' to state ' + values.new_status);

    const opts = {
      method: 'POST',
      url: '{{url('/admin/pireps')}}/' + values.pirep_id + '/status',
      data: values,
    };

    const response = await phpvms.request(opts);
    fn(response.data);
  };

  $(document).ready(() => {
    const select_id = "select#aircraft_select";
    const destContainer = $('#fares_container');

    $(select_id).change(async (e) => {
      const aircraft_id = $(select_id + " option:selected").val();
      const response = await phpvms.request("{{ url('/admin/pireps/fares') }}?aircraft_id=" + aircraft_id);
      destContainer.html(response.data);
    });

    $(document).on('submit', 'form.pjax_form', (event) => {
      event.preventDefault();
      $.pjax.submit(event, '#pirep_comments_wrapper', {push: false});
    });

    $(document).on('pjax:complete', function () {
      initPlugins();
    });

    $('button#recalculate-finances').on('click', async (event) => {
      event.preventDefault();
      const pirep_id = $(event.currentTarget).attr('data-pirep-id');
      const opts = {
        method: 'POST',
        url: '{{url('/api/pireps')}}/' + pirep_id + '/finances/recalculate',
      };
      const response = await phpvms.request(opts);
      console.log(response.data);
      location.reload();
    });

    // ── Status change with modal intercept ────────────────────────────────
    let pendingChange = null;

    const STATE_ACCEPTED = parseInt('{{ App\Models\Enums\PirepState::ACCEPTED }}');
    const STATE_REJECTED = parseInt('{{ App\Models\Enums\PirepState::REJECTED }}');

    const executeStatusChange = (pending, adminComment) => {
      const values = {
        pirep_id:      pending.pirep_id,
        new_status:    pending.new_status,
        admin_comment: adminComment,
      };

      if (pending.onEditPage) {
        changeStatus(values, () => { location.reload(); });
      } else {
        changeStatus(values, (data) => {
          $('#pirep_' + values.pirep_id + '_actionbar').html(data);

          const statusContainer = '#pirep_' + values.pirep_id + '_status_container';
          let badgeClass, badgeText;
          if (parseInt(values.new_status) === STATE_ACCEPTED) {
            badgeClass = 'badge badge-success';
            badgeText  = 'Accepted';
          } else if (parseInt(values.new_status) === STATE_REJECTED) {
            badgeClass = 'badge badge-danger';
            badgeText  = 'Rejected';
          }
          if (badgeClass) {
            $(statusContainer).children().first().removeClass().addClass(badgeClass).html(badgeText);
          }
        });
      }
    };

    $(document).on('submit', 'form.pirep_submit_status, form.pirep_change_status', (event) => {
      event.preventDefault();
      const $form = $(event.currentTarget);
      pendingChange = {
        pirep_id:   $form.attr('pirep_id'),
        new_status: $form.attr('new_status'),
        onEditPage: $form.hasClass('pirep_change_status'),
      };

      const newStatus = parseInt(pendingChange.new_status);
      if (newStatus === STATE_REJECTED) {
        $('#adminRejectReason').val('');
        $('#adminRejectReasonError').hide();
        $('#adminRejectModal').modal('show');
      } else if (newStatus === STATE_ACCEPTED) {
        $('#adminAcceptNote').val('');
        $('#adminAcceptModal').modal('show');
      } else {
        executeStatusChange(pendingChange, '');
      }
    });

    $('#adminRejectConfirm').on('click', () => {
      const reason = $('#adminRejectReason').val().trim();
      if (!reason) {
        $('#adminRejectReasonError').show();
        return;
      }
      $('#adminRejectModal').modal('hide');
      executeStatusChange(pendingChange, reason);
    });

    $('#adminAcceptConfirm').on('click', () => {
      const note = $('#adminAcceptNote').val().trim();
      $('#adminAcceptModal').modal('hide');
      executeStatusChange(pendingChange, note);
    });
  });
</script>
@include('admin.scripts.airport_search')
@endsection
