<?php
/*
 Plugin Name: Pod App
 Description: A plugin to analyze, visualize, compare, and track smartwatch data. Features include advanced stats, trend analysis, data comparison, CSV export, and health suggestions.
 Version: 1.0
 Author: Tao Zhou
*/

// 获取所有包含智能手表数据的文章
function get_all_smartwatch_data($start_date = null, $end_date = null) {
    $args = array(
        'post_type' => 'post', 
        'posts_per_page' => -1, 
        'date_query' => array(
            array(
                'after' => $start_date,
                'before' => $end_date,
                'inclusive' => true,
            ),
        ),
    );

    $query = new WP_Query($args);
    $smartwatch_data_list = array();

    while ($query->have_posts()) {
        $query->the_post();
        $content = get_the_content();

        // 使用正则表达式提取 JSON 格式数据
        if (preg_match('/\{.*?\}/s', $content, $matches)) {
            $json_data = $matches[0];
            $smartwatch_data = json_decode($json_data, true);

            if (json_last_error() === JSON_ERROR_NONE && $smartwatch_data) {
                $smartwatch_data_list[] = $smartwatch_data;
            }
        }
    }

    wp_reset_postdata();
    return $smartwatch_data_list;
}

// 计算标准差
function calculate_standard_deviation($data) {
    if (count($data) === 0) {
        return 0;
    }
    
    $mean = array_sum($data) / count($data);
    $sum_of_squares = 0;
    foreach ($data as $value) {
        $sum_of_squares += pow($value - $mean, 2);
    }
    return sqrt($sum_of_squares / count($data));
}

// 分析智能手表数据，并生成健康建议
function analyze_smartwatch_data($start_date = null, $end_date = null) {
    $smartwatch_data_list = get_all_smartwatch_data($start_date, $end_date);
    
    if (empty($smartwatch_data_list)) {
        return array(
            'average_heart_rate' => 0,
            'max_heart_rate' => 0,
            'min_heart_rate' => 0,
            'std_dev_heart_rate' => 0,
            'average_calories' => 0,
            'max_calories' => 0,
            'min_calories' => 0,
            'std_dev_calories' => 0,
            'average_steps' => 0,
            'max_steps' => 0,
            'min_steps' => 0,
            'std_dev_steps' => 0,
            'average_distance' => 0,
            'max_distance' => 0,
            'min_distance' => 0,
            'std_dev_distance' => 0,
            'heart_rate_data' => array(),
            'calories_data' => array(),
            'steps_data' => array(),
            'distance_data' => array(),
            'total_entries' => 0,
            'health_suggestions' => array() // 添加健康建议
        );
    }

    $total_heart_rate = 0;
    $total_calories = 0;
    $total_steps = 0;
    $total_distance = 0;
    $heart_rate_data = array();
    $calories_data = array();
    $steps_data = array();
    $distance_data = array();
    $count = count($smartwatch_data_list);
    $health_suggestions = array(); // 健康建议列表

    foreach ($smartwatch_data_list as $data) {
        if (isset($data['ST_GetCurrentHeartRateKey'])) {
            $total_heart_rate += $data['ST_GetCurrentHeartRateKey'];
            $heart_rate_data[] = $data['ST_GetCurrentHeartRateKey'];
        }
        if (isset($data['ST_GetCurrentValueCalorieKey'])) {
            $total_calories += $data['ST_GetCurrentValueCalorieKey'];
            $calories_data[] = $data['ST_GetCurrentValueCalorieKey'];
        }
        if (isset($data['ST_GetCurrentValueStepKey'])) {
            $total_steps += $data['ST_GetCurrentValueStepKey'];
            $steps_data[] = $data['ST_GetCurrentValueStepKey'];
        }
        if (isset($data['ST_GetCurrentValueDistanceKey'])) {
            $total_distance += $data['ST_GetCurrentValueDistanceKey'];
            $distance_data[] = $data['ST_GetCurrentValueDistanceKey'];
        }
    }

    // 添加健康建议功能
    $average_heart_rate = round($total_heart_rate / $count, 2);
    if ($average_heart_rate > 100) {
        $health_suggestions[] = "Your average heart rate is quite high. Consider lowering the intensity of your workouts.";
    }
    
    $average_steps = round($total_steps / $count, 2);
    if ($average_steps < 5000) {
        $health_suggestions[] = "Your step count is below the recommended level. Try to increase your daily activity.";
    }

    return array(
        'average_heart_rate' => $average_heart_rate,
        'max_heart_rate' => max($heart_rate_data),
        'min_heart_rate' => min($heart_rate_data),
        'std_dev_heart_rate' => calculate_standard_deviation($heart_rate_data),
        'average_calories' => round($total_calories / $count, 2),
        'max_calories' => max($calories_data),
        'min_calories' => min($calories_data),
        'std_dev_calories' => calculate_standard_deviation($calories_data),
        'average_steps' => $average_steps,
        'max_steps' => max($steps_data),
        'min_steps' => min($steps_data),
        'std_dev_steps' => calculate_standard_deviation($steps_data),
        'average_distance' => round($total_distance / $count, 2),
        'max_distance' => max($distance_data),
        'min_distance' => min($distance_data),
        'std_dev_distance' => calculate_standard_deviation($distance_data),
        'heart_rate_data' => $heart_rate_data,
        'calories_data' => $calories_data,
        'steps_data' => $steps_data,
        'distance_data' => $distance_data,
        'total_entries' => $count,
        'health_suggestions' => $health_suggestions // 返回健康建议
    );
}

// 创建后台菜单页面
function smartwatch_data_menu() {
    add_menu_page(
        'Pod App', 
        'Pod App', 
        'manage_options', 
        'smartwatch-data-analysis', 
        'smartwatch_data_analysis_page', 
        'dashicons-chart-line', 
        20 
    );
}
add_action('admin_menu', 'smartwatch_data_menu');

// 引入 Chart.js 和样式
function smartwatch_data_enqueue_scripts($hook) {
    if ($hook != 'toplevel_page_smartwatch-data-analysis') {
        return;
    }
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true);
}
add_action('admin_enqueue_scripts', 'smartwatch_data_enqueue_scripts');

// 后台页面内容，增加健康建议
function smartwatch_data_analysis_page() {
    if (isset($_POST['start_date']) && isset($_POST['end_date'])) {
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
    } else {
        $start_date = null;
        $end_date = null;
    }

    // 获取分析结果
    $analysis = analyze_smartwatch_data($start_date, $end_date);
    
    echo '<div class="wrap">';
    echo '<h1>Smartwatch Data Analysis with Health Suggestions</h1>';
    
    // 日期选择表单
    echo '<form method="POST">';
    echo '<label for="start_date">Start Date: </label>';
    echo '<input type="date" name="start_date" required>';
    echo '<label for="end_date" style="margin-left:20px;">End Date: </label>';
    echo '<input type="date" name="end_date" required>';
    echo '<input type="submit" class="button button-primary" value="Analyze">';
    echo '</form>';

    if ($analysis['total_entries'] == 0) {
        echo "<p>No smartwatch data available or found.</p>";
    } else {
        echo "<p><strong>Heart Rate:</strong></p>";
        echo "<p>Average: " . $analysis['average_heart_rate'] . " bpm</p>";
        echo "<p>Max: " . $analysis['max_heart_rate'] . " bpm</p>";
        echo "<p>Min: " . $analysis['min_heart_rate'] . " bpm</p>";
        echo "<p>Standard Deviation: " . $analysis['std_dev_heart_rate'] . " bpm</p>";

        echo "<p><strong>Calories:</strong></p>";
        echo "<p>Average: " . $analysis['average_calories'] . " kcal</p>";
        echo "<p>Max: " . $analysis['max_calories'] . " kcal</p>";
        echo "<p>Min: " . $analysis['min_calories'] . " kcal</p>";
        echo "<p>Standard Deviation: " . $analysis['std_dev_calories'] . " kcal</p>";

        echo "<p><strong>Steps:</strong></p>";
        echo "<p>Average: " . $analysis['average_steps'] . "</p>";
        echo "<p>Max: " . $analysis['max_steps'] . "</p>";
        echo "<p>Min: " . $analysis['min_steps'] . "</p>";
        echo "<p>Standard Deviation: " . $analysis['std_dev_steps'] . "</p>";

        echo "<p><strong>Distance:</strong></p>";
        echo "<p>Average: " . $analysis['average_distance'] . " meters</p>";
        echo "<p>Max: " . $analysis['max_distance'] . " meters</p>";
        echo "<p>Min: " . $analysis['min_distance'] . " meters</p>";
        echo "<p>Standard Deviation: " . $analysis['std_dev_distance'] . " meters</p>";

        // 显示健康建议
        if (!empty($analysis['health_suggestions'])) {
            echo "<h2>Health Suggestions:</h2>";
            foreach ($analysis['health_suggestions'] as $suggestion) {
                echo "<p>$suggestion</p>";
            }
        }

        // 心率图表按钮
        echo '<button id="showHeartRateChart" class="button button-primary">Show Heart Rate Chart</button>';
        echo '<canvas id="heartRateChart" style="max-width: 600px; max-height: 400px; display: none;"></canvas>';
    }

    // 图表显示的 JavaScript
    ?>
    <script>
    document.getElementById('showHeartRateChart').addEventListener('click', function() {
        var ctx = document.getElementById('heartRateChart').getContext('2d');
        document.getElementById('heartRateChart').style.display = 'block';
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: [<?php echo implode(',', range(1, count($analysis['heart_rate_data']))); ?>],
                datasets: [{
                    label: 'Heart Rate (bpm)',
                    data: [<?php echo implode(',', $analysis['heart_rate_data']); ?>],
                    borderColor: 'rgba(75, 192, 192, 1)',
                    fill: false,
                }]
            }
        });
    });
    </script>
    <?php
    echo '</div>';
}
