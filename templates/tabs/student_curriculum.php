<div class="tab-pane fade" id="curriculum" role="tabpanel">
    <div class="curriculum-section">
        <h3><i class="fas fa-tasks"></i> 必修課程進度</h3>

        <div class="card border-0">
            <div class="card-body p-0">
                <table class="table curriculum-table table-hover mb-0">
                    <thead>
                        <tr>
                            <th width="30%">類別</th>
                            <th>修課情況</th>
                        </tr>
                    </thead>
                    <tbody id="student-curriculum-tbody">
                        <!-- 非同步載入 -->
                        <tr>
                            <td colspan="2" class="text-center py-4">
                                <div class="loading-skeleton">
                                    <div class="skeleton-pulse" style="height: 40px;"></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="legend-box">
            <div><i class="far fa-play-circle status-icon icon-red"></i> 尚未選課</div>
            <div><i class="fas fa-exclamation-circle status-icon icon-yellow"></i> 已選課，未完成</div>
            <div><i class="fas fa-check-circle status-icon icon-green"></i> 已完成</div>
            <div><i class="fas fa-times-circle status-icon icon-black"></i> 未通過</div>
        </div>
    </div>
</div>