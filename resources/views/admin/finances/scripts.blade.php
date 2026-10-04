@section('scripts')
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script>
    $(document).ready(() => {
      const series = @json($series);

      function barChart(id, rows, labelFn) {
        const el = document.getElementById(id);
        if (!el || !rows.length || typeof Chart === 'undefined') return;
        new Chart(el, {
          type: 'bar',
          data: {
            labels: rows.map(labelFn),
            datasets: [
              {label: 'Income', data: rows.map(r => r.income), backgroundColor: 'rgba(76,175,118,0.7)'},
              {label: 'Costs', data: rows.map(r => r.cost), backgroundColor: 'rgba(224,82,82,0.7)'},
              {label: 'Profit', data: rows.map(r => r.profit), backgroundColor: 'rgba(74,144,217,0.9)'}
            ]
          },
          options: {responsive: true, plugins: {tooltip: {callbacks: {label: c => c.dataset.label + ': ' + c.parsed.y.toLocaleString() + ' USD'}}}}
        });
      }

      barChart('chartAnnual', series.annual, r => r.label);
      barChart('chartMonthly', series.monthly, r => r.label.slice(5));
      barChart('chartDaily', series.daily, r => r.label.slice(8));
    });
  </script>
  <script>
    $(document).ready(() => {
      const select_id = "select#month_select";
      $(select_id).change((e) => {
        const date = $(select_id + " option:selected").val();
        const location = window.location.toString().split('?')[0];
        window.location = location + '?month=' + date;
      });
    });
  </script>
@endsection
